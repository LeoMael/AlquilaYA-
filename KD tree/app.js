
class KDNode {
    constructor(point, axis, name) {
      this.point = point; // Coordenadas [latitud, longitud]
      this.name = name; // Nombre del restaurante
      this.left = null;
      this.right = null;
      this.axis = axis; // Eje de división: 0 (latitud), 1 (longitud)
    }
  }
  
  function buildKDTree(points, depth = 0) {
    if (points.length === 0) return null;
  
    const axis = depth % 2; 
    points.sort((a, b) => a.coords[axis] - b.coords[axis]); // Ordena los puntos según el eje
    const median = Math.floor(points.length / 2); // Encuentra el punto medio
  
    const node = new KDNode(points[median].coords, axis, points[median].name); // Crea el nodo actual
    node.left = buildKDTree(points.slice(0, median), depth + 1); // Subárbol izquierdo
    node.right = buildKDTree(points.slice(median + 1), depth + 1); // Subárbol derecho
  
    return node;
  }
  
  function distanceSquared(point1, point2) {
    return (point1[0] - point2[0]) ** 2 + (point1[1] - point2[1]) ** 2;
  }
  
  function nearestNeighborSearch(node, target, depth = 0, best = null) {
    if (node === null) return best;
  
    const axis = node.axis;
    let nextBest = best;
  
    if (best === null || distanceSquared(target, node.point) < distanceSquared(target, nextBest.point)) {
      nextBest = node;
    }
  
    const nextBranch = target[axis] < node.point[axis] ? 'left' : 'right';
  
    nextBest = nearestNeighborSearch(node[nextBranch], target, depth + 1, nextBest);
  
    if ((target[axis] - node.point[axis]) ** 2 < distanceSquared(target, nextBest.point)) {
      const otherBranch = nextBranch === 'left' ? 'right' : 'left';
      nextBest = nearestNeighborSearch(node[otherBranch], target, depth + 1, nextBest);
    }
  
    return nextBest;
  }
  
  const restaurants = [
    { coords: [-15.835865791357467, -70.01584033971989], name: "Don tico" },
    { coords: [-15.836345960183804, -70.01796324344342], name: "La trucha oro" },
    { coords: [-15.845842094300231, -70.01677046568294], name: "Cevicheria Muelles" },
    { coords: [-15.83509203114286, -70.02365950650025], name: "Cevichería el camanejo" },
    { coords: [-15.836169459631408, -70.02331700238365], name: "La Karpa" }
  ];
  
  const kdTree = buildKDTree(restaurants);
  
  const map = L.map('map').setView([-15.840221, -70.021881], 15);
  
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(map);
  
  restaurants.forEach(restaurant => {
    L.marker(restaurant.coords).addTo(map).bindPopup(`Nombre: ${restaurant.name}`);
  });
  
  let currentRoute = null;
  
  // Escuchar eventos de clic en el mapa
  map.on('click', function(e) {
    const userLocation = [e.latlng.lat, e.latlng.lng]; // Ubicación del clic del usuario
    const nearestRestaurant = nearestNeighborSearch(kdTree, userLocation); // Buscar restaurante más cercano
  
 
    document.getElementById("resultado").textContent = `Buscando la ruta más cercana...`;
  
    if (currentRoute) {
      currentRoute.remove();
    }
  
    currentRoute = L.Routing.control({
      waypoints: [
        L.latLng(userLocation[0], userLocation[1]), 
        L.latLng(nearestRestaurant.point[0], nearestRestaurant.point[1])
      ],
      router: L.Routing.osrmv1({
        serviceUrl: 'https://router.project-osrm.org/route/v1'
      }),
      show: true,
      routeWhileDragging: false,
      addWaypoints: false,
      lineOptions: {
        styles: [{ color: 'blue', opacity: 0.6, weight: 5 }]
      },
      createMarker: function(i, waypoint) {
        return L.marker(waypoint.latLng).bindPopup(i === 0 ? 'Tu ubicación' : `Restaurante más cercano: ${nearestRestaurant.name}`);
      }
    }).addTo(map);
  
    currentRoute.on('routesfound', function(e) {
      const distance = e.routes[0].summary.totalDistance; // Distancia en metros
      document.getElementById("resultado").textContent = `Restaurante más cercano: ${nearestRestaurant.name}, a ${Math.round(distance)} metros.`;
    });
  });
  
  