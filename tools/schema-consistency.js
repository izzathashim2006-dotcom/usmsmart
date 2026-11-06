#!/usr/bin/env node
/*
 * Compares the MySQL tables and columns the application creates at runtime
 * with database/schema.sql.
 *
 *   node tools/schema-consistency.js
 *
 * Every CREATE TABLE, ALTER TABLE ... ADD [COLUMN] and static INSERT INTO in
 * the PHP sources is collected and looked up in the schema file. A table or
 * column the code depends on but the file does not declare would only show up
 * after a fresh install fails at query time, so this check fails first. The
 * INSERT targets matter because students_view and the uda_* tables are legacy
 * tables the application writes to without ever creating them.
 *
 * The reverse is allowed: schema.sql may hold extra tables or legacy columns.
 *
 * No database connection is used; this is a text check that runs in CI.
 */

'use strict';

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const schemaPath = path.join(root, 'database', 'schema.sql');

const CONSTRAINT_WORDS = new Set([
  'PRIMARY', 'UNIQUE', 'KEY', 'INDEX', 'CONSTRAINT', 'FOREIGN',
  'CHECK', 'FULLTEXT', 'SPATIAL',
]);

function phpSources(dir) {
  const files = [];
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    if (entry.name === 'node_modules' || entry.name === 'vendor' || entry.name === '.git') continue;
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) files.push(...phpSources(full));
    else if (entry.name.endsWith('.php')) files.push(full);
  }
  return files.sort();
}

// Returns the index just past the parenthesis that closes the one at `open`.
// Parentheses inside quoted SQL literals are ignored, so ENUM('a','b') and
// CHECK (json_valid(...)) do not end the table body early.
function matchingParen(text, open) {
  let depth = 0;
  let quote = '';
  for (let i = open; i < text.length; i++) {
    const ch = text[i];
    if (quote) {
      if (ch === '\\' && quote === "'") { i++; continue; }
      if (ch === quote) {
        if (quote === "'" && text[i + 1] === "'") { i++; continue; }
        quote = '';
      }
      continue;
    }
    if (ch === "'" || ch === '"' || ch === '`') { quote = ch; continue; }
    if (ch === '(') depth++;
    else if (ch === ')') {
      depth--;
      if (depth === 0) return i + 1;
    }
  }
  return -1;
}

// Splits a CREATE TABLE body on commas that are not nested in parentheses or
// quotes.
function splitTopLevel(body) {
  const parts = [];
  let start = 0;
  let depth = 0;
  let quote = '';
  for (let i = 0; i < body.length; i++) {
    const ch = body[i];
    if (quote) {
      if (ch === '\\' && quote === "'") { i++; continue; }
      if (ch === quote) {
        if (quote === "'" && body[i + 1] === "'") { i++; continue; }
        quote = '';
      }
      continue;
    }
    if (ch === "'" || ch === '"' || ch === '`') { quote = ch; continue; }
    if (ch === '(') depth++;
    else if (ch === ')') depth--;
    else if (ch === ',' && depth === 0) {
      parts.push(body.slice(start, i));
      start = i + 1;
    }
  }
  parts.push(body.slice(start));
  return parts;
}

function columnsFromBody(body) {
  const columns = [];
  for (const part of splitTopLevel(body)) {
    const definition = part.trim();
    if (!definition) continue;
    const name = definition.match(/^`?([A-Za-z0-9_]+)`?/);
    if (!name) continue;
    if (CONSTRAINT_WORDS.has(name[1].toUpperCase())) continue;
    columns.push(name[1]);
  }
  return columns;
}

function parseCreateTables(text) {
  const tables = new Map();
  const pattern = /CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([A-Za-z0-9_]+)`?\s*\(/gi;
  let match;
  while ((match = pattern.exec(text)) !== null) {
    const open = match.index + match[0].length - 1;
    const close = matchingParen(text, open);
    if (close < 0) continue;
    const table = match[1];
    const existing = tables.get(table) || new Set();
    for (const column of columnsFromBody(text.slice(open + 1, close - 1))) existing.add(column);
    tables.set(table, existing);
  }
  return tables;
}

function parseAlterColumns(text) {
  const added = new Map();
  const pattern = /ALTER\s+TABLE\s+`?([A-Za-z0-9_]+)`?\s+ADD\s+(?:COLUMN\s+)?(?:IF\s+NOT\s+EXISTS\s+)?`?([A-Za-z0-9_]+)`?/gi;
  let match;
  while ((match = pattern.exec(text)) !== null) {
    const table = match[1];
    const column = match[2];
    if (CONSTRAINT_WORDS.has(column.toUpperCase())) continue;
    const existing = added.get(table) || new Set();
    existing.add(column);
    added.set(table, existing);
  }
  return added;
}

function parseInsertTargets(text) {
  const tables = new Set();
  const pattern = /INSERT\s+INTO\s+`?([A-Za-z0-9_]+)`?/gi;
  let match;
  while ((match = pattern.exec(text)) !== null) tables.add(match[1]);
  return tables;
}

const schema = parseCreateTables(fs.readFileSync(schemaPath, 'utf8'));
const problems = [];
let createdTables = 0;
let checkedColumns = 0;
const insertTables = new Set();

for (const file of phpSources(root)) {
  const text = fs.readFileSync(file, 'utf8');
  const relative = path.relative(root, file).split(path.sep).join('/');

  for (const [table, columns] of parseCreateTables(text)) {
    createdTables++;
    if (!schema.has(table)) {
      problems.push(`${relative}: table ${table} is created by the code but missing from database/schema.sql`);
      continue;
    }
    const known = schema.get(table);
    for (const column of columns) {
      checkedColumns++;
      if (!known.has(column)) {
        problems.push(`${relative}: ${table}.${column} is created by the code but missing from database/schema.sql`);
      }
    }
  }

  for (const [table, columns] of parseAlterColumns(text)) {
    if (!schema.has(table)) {
      problems.push(`${relative}: ALTER TABLE ${table} adds columns but the table is missing from database/schema.sql`);
      continue;
    }
    const known = schema.get(table);
    for (const column of columns) {
      checkedColumns++;
      if (!known.has(column)) {
        problems.push(`${relative}: ${table}.${column} is added by the code but missing from database/schema.sql`);
      }
    }
  }

  for (const table of parseInsertTargets(text)) {
    insertTables.add(table);
    if (!schema.has(table)) {
      problems.push(`${relative}: INSERT INTO ${table} needs a table that is missing from database/schema.sql`);
    }
  }
}

if (problems.length) {
  console.error('Schema drift detected:');
  for (const problem of problems) console.error('  - ' + problem);
  console.error('');
  console.error('Update database/schema.sql (or tools/dump-schema.php output) so a fresh install matches the code.');
  process.exit(1);
}

console.log(`schema ok: ${createdTables} runtime tables, ${checkedColumns} column references and ${insertTables.size} write targets checked against ${schema.size} schema tables`);
