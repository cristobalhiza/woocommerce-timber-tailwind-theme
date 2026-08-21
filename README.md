# InfinityLove — tema WooCommerce sobre Timber

Tema a medida para una tienda chilena de venta al por mayor y detalle. Se
publica como referencia de cómo queda un WooCommerce sin plugins de maquetación
ni de filtros: plantillas Twig, carrito sobre la Store API y todo el marcado de
la tienda repintado a mano.

- **Timber v2** para las plantillas, con la integración de
  [mindkomm/timber-integration-woocommerce](https://github.com/mindkomm/timber-integration-woocommerce).
- **Tailwind v4** compilado con Vite, con manifest y nombres con hash.
- **Alpine.js** para lo poco que necesita estado en el cliente: carrito,
  carrusel, galería, rango de precio.
- Sin jQuery propio, sin librería de sliders, sin plugin de campos
  personalizados y sin plugin de filtros.

## Requisitos

| | |
|---|---|
| WordPress | 6.5 o superior |
| PHP | 8.1 o superior |
| WooCommerce | 9 o superior (probado en 11) |
| Node | 20 o superior, solo para construir |

## Puesta en marcha

```bash
composer install --no-dev   # Timber y la integración de WooCommerce
npm install
npm run build               # deja dist/ con el manifest
npm run watch               # durante el desarrollo
npm test                    # comprobaciones de carrito, carrusel y precio
```

`vendor/` y `dist/` no se versionan: se construyen antes de desplegar y viajan
con el tema. Si falta el manifest, el tema **no** sirve una página sin estilos:
registra el error en el log y lo dice.

Con DDEV conviene apuntar Composer al tema, en `.ddev/config.yaml`:

```yaml
composer_root: "wp-content/themes/[carpeta-del-tema]"
```

## Cómo está organizado

```
functions.php          carga los módulos de includes/ en orden
includes/
  setup.php            soportes del tema, tamaños de imagen, menús
  assets.php           encolado leyendo el manifest de Vite
  timber.php           opciones de Twig y filtros propios
  woocommerce.php      integración, facetas de la tienda, ganchos del bucle
  campos.php           metabox propio de campos por plantilla
  ajustes.php          pantalla de ajustes globales (Settings API)
  cpt.php              tipos de contenido propios
  portada.php          consultas y mínimos de cada sección de la portada
  login.php            pantalla de acceso con la identidad del sitio
  suscripcion.php      alta al boletín guardada como CPT privado
templates/             plantillas del tema (Twig)
views/woocommerce/     plantillas que sustituyen a las de WooCommerce
src/                   CSS y JS de origen; los .test.mjs corren con node a secas
```

## Decisiones que explican el código

Cada una está argumentada en el comentario del fichero correspondiente. Las que
más sorprenden al leerlo por encima:

- **Las tres hojas clásicas de WooCommerce están retiradas**
  (`woocommerce_enqueue_styles`). El tema repinta todo el marcado que usa, así
  que aplicarlas solo produce conflictos. Las estrellas de valoración se dibujan
  con el carácter ★ en CSS, sin la tipografía de iconos.
- **El carrito habla con la Store API**, no con `wc-ajax` ni con fragments: un
  `Alpine.store` con bootstrap por GET, reintento ante nonce caducado y estado
  reemplazado por la respuesta del servidor en cada mutación.
- **Los filtros de la tienda no usan el widget de navegación por capas.** Los
  parámetros (`filter_<atributo>`, `min_price`, `max_price`) los resuelve el
  núcleo de WooCommerce; el tema solo pinta enlaces. Los recuentos se calculan
  dentro de la categoría y de los demás filtros puestos, porque el `count` del
  término es global y promete resultados que no existen.
- **El rango de precio son dos `<input type="range">` nativos superpuestos.** El
  navegador ya trae el arrastre, el teclado y el táctil.
- **Los campos editables son un metabox propio** de unas setenta líneas en vez
  de ACF o SCF: con texto, textarea e imagen sobre páginas, el plugin no se
  paga en un sitio que se despliega por FTP.
- **En Tailwind v4, el CSS propio sin `@layer` gana a las utilidades.** Las
  clases del tema que se componen con utilidades van en `@layer components`.

## Qué no está aquí

El núcleo de WordPress, los `uploads` y las herramientas de importación del
catálogo del cliente, que son específicas de su tienda. El tema no depende de
ninguna de las tres.

## Licencia

GPL-2.0-or-later, como WordPress.
