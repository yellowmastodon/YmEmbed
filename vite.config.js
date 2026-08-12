import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import fs from 'fs';
import { resolve, dirname } from 'path';

function forceInlineSharedCore() {
  return {
    name: 'force-inline-shared-core',
    // enforce: 'pre' ensures Vite/Rollup doesn't try to normalize the path first
    enforce: 'pre', 
    resolveId(source, importer) {
      if (importer && source.includes('lazyframe-core')) {
        let cleanSource = source;
        if (cleanSource.startsWith('./') || cleanSource.startsWith('../')) {
          cleanSource = resolve(dirname(importer), cleanSource);
        }
        
        // The \0 prefix makes Rollup treat this as a virtual module.
        // Appending the importer path makes it a 100% unique ID per entry point.
        return `\0${cleanSource}?for=${encodeURIComponent(importer)}`;
      }
    },
    load(id) {
      if (id.startsWith('\0') && id.includes('lazyframe-core')) {
        // Extract the real file path from our virtual ID
        const realPath = id.replace('\0', '').split('?for=')[0];
        return fs.readFileSync(realPath, 'utf-8');
      }
    },
  };
}

export default defineConfig({
  plugins: [
    forceInlineSharedCore(),
    laravel({
      input: [
        'src/InputfieldEmbed.js',
        'src/InputfieldEmbed.scss',
        'src/lazyframe.js',
        'src/lazyframe.scss',
      ],
      refresh: false,
      hotFile: false,
    }),
  ],

  build: {
    outDir: 'build',
    emptyOutDir: true,
    manifest: false,
    rollupOptions: {
      output: {
        entryFileNames: '[name].js',
        chunkFileNames: '[name].js',
        assetFileNames: '[name][extname]',
      },
    },
  },
});