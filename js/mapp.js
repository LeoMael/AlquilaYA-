// Inicializar el mapa centrado en Puno
const map = L.map('map').setView([-15.840221, -70.021881], 15);

// Agregar el mapa de OpenStreetMap
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
}).addTo(map);

// Asegúrate de que `window.casasCoordenadas` esté disponible
const coordenadas = window.casasCoordenadas;
window.casasCoordenadas.forEach(casa => {
    L.marker([casa.latitud, casa.logitud]).addTo(map).bindPopup(`Dirección: ${casa.direccion}`);
});
// Colocar marcadores para los restaurantes

