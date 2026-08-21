import './input.css';
import Alpine from 'alpinejs';
import { registrarGaleria, iniciarSwatches, iniciarContadores } from './producto.js';
import { registrarCarrusel } from './carrusel.js';
import { registrarCarrito, iniciarCarrito } from './carrito.js';
import { registrarPrecio } from './precio.js';

window.Alpine = Alpine;

registrarGaleria(Alpine);
registrarCarrusel(Alpine);
registrarCarrito(Alpine);
registrarPrecio(Alpine);

Alpine.start();

// El script se encola como módulo, así que el DOM ya está construido aquí.
iniciarSwatches();
iniciarContadores();
iniciarCarrito(Alpine);
