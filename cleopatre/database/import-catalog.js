#!/usr/bin/env node
// CLÉOPÂTRE — Import catalogue Node helper
// Lit data/*.js et insère dans SQLite via better-sqlite3 ou sqlite3
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const ROOT = path.resolve(__dirname, '..');
const DB_PATH = path.join(ROOT, 'database', 'cleopatre.sqlite');

function loadJsData(file) {
  const code = fs.readFileSync(file, 'utf8');
  const sandbox = { window: {} };
  vm.createContext(sandbox);
  vm.runInContext(code, sandbox);
  return sandbox.window;
}

const prodData = loadJsData(path.join(ROOT, 'data', 'products.js'));
const brandData = loadJsData(path.join(ROOT, 'data', 'brands.js'));
const catData = loadJsData(path.join(ROOT, 'data', 'categories.js'));

const products = prodData.CLEO_PRODUCTS || [];
const brands = prodData.CLEO_BRANDS || brandData.CLEO_BRANDS || [];
const categories = prodData.CLEO_CATEGORIES || catData.CLEO_CATEGORIES || [];
const concerns = prodData.CLEO_CONCERNS || [];
const articles = prodData.CLEO_ARTICLES || [];

// Fallback: if categories not found, try separate file
let finalCats = categories;
if (!finalCats.length) {
  try {
    const c = loadJsData(path.join(ROOT, 'data', 'categories.js'));
    finalCats = c.CLEO_CATEGORIES || [];
  } catch(e){ console.warn("[import] categories fallback failed", e.message); }
}
let finalBrands = brands;
if (!finalBrands.length) {
  try {
    const b = loadJsData(path.join(ROOT, 'data', 'brands.js'));
    finalBrands = b.CLEO_BRANDS || [];
  } catch(e){ console.warn("[import] brands fallback failed", e.message); }
}

// Build output JSON for PHP helper
const out = {
  products: products.map(p => ({
    id: p.id,
    brand: p.brand,
    name: p.name,
    cat: p.cat,
    sub: p.sub || null,
    form: p.form || null,
    tint: p.tint || null,
    price: p.price,
    oldPrice: p.oldPrice ?? null,
    size: p.size || null,
    concerns: p.concerns || [],
    rating: p.rating || 0,
    reviews: p.reviews || 0,
    stock: p.stock ? 1 : 0,
    featured: p.featured ? 1 : 0,
    bestseller: p.bestseller || null,
    image: p.image || null,
    imageAlt: p.imageAlt || p.image || null,
    imageThumb: p.imageThumb || null,
    short: p.short || null,
    description: p.description || null,
    ingredients: p.ingredients || null,
    benefits: p.benefits || [],
    usage: p.usage || null,
  })),
  brands: finalBrands.map(b => ({
    slug: b.slug,
    name: b.name,
    country: b.country || null,
    est: b.est || null,
    letter: b.letter || null,
    featured: b.featured ? 1 : 0,
    tint: b.tint || null,
    tagline: b.tagline || null,
    story: b.story || [],
    signature: b.signature || null,
    values: b.values || [],
  })),
  categories: finalCats.map(c => ({
    slug: c.slug,
    name: c.name,
    eyebrow: c.eyebrow || null,
    tagline: c.tagline || null,
    description: c.description || null,
    intro: c.intro || null,
    accent: c.accent || null,
    surface: c.surface || null,
    form: c.form || null,
    keywords: c.keywords || [],
  })),
  concerns,
  articles
};

const outPath = path.join(ROOT, 'database', 'catalog.json');
fs.writeFileSync(outPath, JSON.stringify(out, null, 2), 'utf8');
console.log(`Exported ${out.products.length} products, ${out.brands.length} brands, ${out.categories.length} categories to ${outPath}`);

// Also try direct SQLite insert if sqlite3 available
let didDb = false;
try {
  let Database;
  try { Database = require('better-sqlite3'); } catch(e) {
    console.warn("[import] better-sqlite3 not available", e.message);
    try { Database = require('sqlite3').Database; } catch(e2) { console.warn("[import] sqlite3 not available", e2.message); throw e; }
  }
  if (Database) {
    console.log('DB module found, but letting PHP handle import for consistency');
  }
} catch(e) {
  console.warn("[import] No sqlite driver, JSON export only — PHP will import. Reason:", e.message);
}
