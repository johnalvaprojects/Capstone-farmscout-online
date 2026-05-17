/**
 * Remove stale Vite output under app/assets/*.js and app/assets/*.css
 * (keeps only the graph reachable from app/index.html entry + video/fonts/images dirs).
 *
 * Run from repo root: node scripts/prune-vite-assets.mjs
 */
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, "..");
const assetsDir = path.join(root, "app", "assets");

const keep = new Set();

function scan(file) {
  if (keep.has(file)) return;
  keep.add(file);
  const p = path.join(assetsDir, file);
  if (!fs.existsSync(p)) {
    console.warn("missing (referenced but absent):", file);
    return;
  }
  const s = fs.readFileSync(p, "utf8");
  for (const r of [
    /assets\/([a-zA-Z0-9_.-]+\.(js|css))/g,
    /\/farmscout_online\/app\/assets\/([a-zA-Z0-9_.-]+\.(js|css))/g,
    /\.\/([a-zA-Z0-9_.-]+\.(js|css))/g,
  ]) {
    let m;
    while ((m = r.exec(s))) scan(m[1]);
  }
}

const html = fs.readFileSync(path.join(root, "app", "index.html"), "utf8");
const entryMatch = html.match(/app\/assets\/(index-[a-zA-Z0-9_.-]+\.js)/);
if (!entryMatch) {
  console.error("Could not find app/assets/index-*.js in app/index.html");
  process.exit(1);
}
scan(entryMatch[1]);
const htmlCss = html.match(/app\/assets\/([a-zA-Z0-9_.-]+\.css)/g);
if (htmlCss) {
  for (const h of htmlCss) {
    const m = h.match(/assets\/([a-zA-Z0-9_.-]+\.css)/);
    if (m) scan(m[1]);
  }
}

const entries = fs.readdirSync(assetsDir, { withFileTypes: true });
let removed = 0;
for (const d of entries) {
  if (!d.isFile()) continue;
  const name = d.name;
  if (!/\.(js|css)$/i.test(name)) continue;
  if (keep.has(name)) continue;
  fs.unlinkSync(path.join(assetsDir, name));
  removed++;
}

console.log("Keeping", keep.size, "JS/CSS files:", [...keep].sort().join(", "));
console.log("Removed", removed, "stale JS/CSS files from app/assets/");
