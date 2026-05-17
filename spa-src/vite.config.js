import { defineConfig } from "vite";
import { fileURLToPath } from "node:url";
import path from "node:path";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

export default defineConfig({
  // IMPORTANT: your deployed base for the SPA (matches /app/index.html <base>)
  base: "/farmscout_online/app/",
  build: {
    // Build straight into the existing /app folder (compiled output)
    outDir: path.resolve(__dirname, "../app"),
    emptyOutDir: false,
    assetsDir: "assets",
    sourcemap: false,
  },
  server: {
    port: 5173,
  },
});

