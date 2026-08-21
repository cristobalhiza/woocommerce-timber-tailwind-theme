import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

// El nombre con hash es la única invalidación de assets que no depende de
// Varnish (design.md — decisión 1). De ahí manifest: true y outDir dist/,
// que se sube por FTP junto al resto del tema.
export default defineConfig({
  // Las url() que Vite escribe dentro del CSS —las fuentes, sin ir más lejos—
  // salen absolutas al dominio con la base por defecto ('/'), y en un tema de
  // WordPress eso apunta a la raíz del sitio: 404. Relativas se resuelven desde
  // el propio fichero CSS, que es donde están.
  base: './',
  plugins: [tailwindcss()],
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      // Dos entradas: el sitio y la pantalla de acceso. El login va aparte
      // porque wp-login.php no debe recibir el reset de Tailwind (src/login.css
      // lo explica); comparte con el sitio los ficheros de fuentes, que Vite
      // emite una sola vez.
      input: ['src/main.js', 'src/login.css'],
    },
  },
});
