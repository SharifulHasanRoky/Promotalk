import { vitePlugin as remix } from "@remix-run/dev";
import { defineConfig } from "vite";
import tsconfigPaths from "vite-tsconfig-paths";

export default defineConfig({
  server: {
    port: Number(process.env.PORT || 3000),
    hmr: { port: 8002 },
    fs: { allow: ["app", "node_modules"] },
  },
  plugins: [remix(), tsconfigPaths()],
  build: { assetsInlineLimit: 0 },
});
