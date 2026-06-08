@extends('layouts.hr-app')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-6">
        <a href="{{ route('office-locations.index') }}" class="text-brand-600 hover:text-brand-800 font-medium text-sm flex items-center">
            <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i> Back to Locations
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
            <h2 class="text-xl font-bold text-slate-800">Add New Office Location</h2>
        </div>
        
        <form action="{{ route('office-locations.store') }}" method="POST" class="p-6">
            @csrf
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Form Fields -->
                <div class="space-y-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Location Name *</label>
                        <input type="text" name="name" id="name" required class="w-full border-slate-300 rounded-lg shadow-sm focus:border-brand-500 focus:ring-brand-500" value="{{ old('name') }}" placeholder="e.g. London HQ">
                        @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="address" class="block text-sm font-medium text-slate-700 mb-1">Address</label>
                        <textarea name="address" id="address" rows="2" class="w-full border-slate-300 rounded-lg shadow-sm focus:border-brand-500 focus:ring-brand-500" placeholder="Full street address">{{ old('address') }}</textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="latitude" class="block text-sm font-medium text-slate-700 mb-1">Latitude *</label>
                            <input type="number" step="0.0000001" name="latitude" id="latitude" required class="w-full border-slate-300 rounded-lg shadow-sm focus:border-brand-500 focus:ring-brand-500" value="{{ old('latitude') }}">
                            @error('latitude') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="longitude" class="block text-sm font-medium text-slate-700 mb-1">Longitude *</label>
                            <input type="number" step="0.0000001" name="longitude" id="longitude" required class="w-full border-slate-300 rounded-lg shadow-sm focus:border-brand-500 focus:ring-brand-500" value="{{ old('longitude') }}">
                            @error('longitude') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label for="radius_meters" class="block text-sm font-medium text-slate-700 mb-1">Geofence Radius (meters) *</label>
                        <input type="number" name="radius_meters" id="radius_meters" required min="50" max="5000" class="w-full border-slate-300 rounded-lg shadow-sm focus:border-brand-500 focus:ring-brand-500" value="{{ old('radius_meters', 100) }}">
                        <p class="text-xs text-slate-500 mt-1">Employees must be within this distance to clock in.</p>
                        @error('radius_meters') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-4 border-t border-slate-200">
                        <label class="flex items-center">
                            <input type="checkbox" name="allow_remote" id="allow_remote" value="1" class="rounded border-slate-300 text-brand-600 shadow-sm focus:border-brand-300 focus:ring focus:ring-brand-200 focus:ring-opacity-50" {{ old('allow_remote') ? 'checked' : '' }}>
                            <span class="ml-2 text-sm text-slate-700 font-medium">Allow Remote Work from this location</span>
                        </label>
                        <p class="text-xs text-slate-500 mt-1 ml-6">If checked, the geofence radius is ignored. Employees assigned to this location can clock in from anywhere.</p>
                    </div>
                </div>

                <!-- Map & Search -->
                <div class="h-full min-h-[400px] flex flex-col space-y-2">
                    <div class="relative">
                        <label for="location-search" class="sr-only">Search Location</label>
                        <div class="flex gap-2">
                            <div class="flex flex-1">
                                <input type="text" id="location-search" class="w-full border-slate-300 rounded-l-lg shadow-sm focus:border-brand-500 focus:ring-brand-500" placeholder="Search for location (e.g. Bahria Town, Rawalpindi)...">
                                <button type="button" id="search-btn" class="bg-slate-100 hover:bg-slate-200 border border-l-0 border-slate-300 rounded-r-lg px-4 text-slate-600 transition">
                                    <i data-lucide="search" class="w-5 h-5"></i>
                                </button>
                            </div>
                            <button type="button" id="current-location-btn" class="bg-slate-100 hover:bg-slate-200 border border-slate-300 rounded-lg px-4 text-brand-600 transition flex items-center justify-center whitespace-nowrap" title="Use my current location">
                                <i data-lucide="crosshair" class="w-5 h-5 mr-1"></i> Current
                            </button>
                        </div>
                        <ul id="search-results" class="absolute z-10 w-full bg-white border border-slate-200 rounded-lg shadow-lg mt-1 max-h-48 overflow-y-auto hidden">
                        </ul>
                    </div>
                    <div id="map-preview" class="flex-grow rounded-lg border border-slate-300 z-0"></div>
                    <p class="text-xs text-slate-500">Search for a place or click on the map to automatically fill coordinates and address.</p>
                </div>
            </div>

            <div class="mt-8 flex justify-end space-x-3">
                <a href="{{ route('office-locations.index') }}" class="bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-medium py-2 px-4 rounded-lg transition">Cancel</a>
                <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-bold py-2 px-6 rounded-lg shadow-sm transition">Save Location</button>
            </div>
        </form>
    </div>
</div>

<!-- Leaflet CSS & JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const latInput = document.getElementById('latitude');
        const lngInput = document.getElementById('longitude');
        const addressInput = document.getElementById('address');
        const searchInput = document.getElementById('location-search');
        const searchBtn = document.getElementById('search-btn');
        const searchResults = document.getElementById('search-results');
        const radiusInput = document.getElementById('radius_meters');

        // Initialize Map
        // Default to a generic view or current inputs if available
        let initialLat = latInput.value ? parseFloat(latInput.value) : 33.5651; // default to Rawalpindi
        let initialLng = lngInput.value ? parseFloat(lngInput.value) : 73.0169;
        let zoom = latInput.value ? 16 : 10;

        const map = L.map('map-preview').setView([initialLat, initialLng], zoom);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        let marker;
        let circle;

        function updateMapAndInputs(lat, lng, radius, addr = null) {
            latInput.value = lat;
            lngInput.value = lng;
            if(addr) addressInput.value = addr;

            if (marker) {
                map.removeLayer(marker);
            }
            if (circle) {
                map.removeLayer(circle);
            }

            marker = L.marker([lat, lng]).addTo(map);
            circle = L.circle([lat, lng], {
                color: '#3B82F6',
                fillColor: '#3B82F6',
                fillOpacity: 0.2,
                radius: parseInt(radius || 100)
            }).addTo(map);

            map.setView([lat, lng], 16);
        }

        if (latInput.value && lngInput.value) {
            updateMapAndInputs(latInput.value, lngInput.value, radiusInput.value);
        }

        // Click on map to set location
        map.on('click', function(e) {
            const lat = e.latlng.lat.toFixed(7);
            const lng = e.latlng.lng.toFixed(7);
            
            // Reverse Geocode to get address automatically
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                .then(res => res.json())
                .then(data => {
                    const addr = data.display_name || '';
                    updateMapAndInputs(lat, lng, radiusInput.value, addr);
                })
                .catch(err => {
                    updateMapAndInputs(lat, lng, radiusInput.value);
                });
        });

        // Radius change update circle
        radiusInput.addEventListener('input', () => {
            if (latInput.value && lngInput.value) {
                updateMapAndInputs(latInput.value, lngInput.value, radiusInput.value);
            }
        });

        // Search Location (Nominatim)
        function performSearch() {
            const query = searchInput.value;
            if (!query) return;

            searchResults.innerHTML = '<li class="p-3 text-sm text-slate-500">Searching...</li>';
            searchResults.classList.remove('hidden');

            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5`)
                .then(res => res.json())
                .then(data => {
                    searchResults.innerHTML = '';
                    if (data.length === 0) {
                        searchResults.innerHTML = '<li class="p-3 text-sm text-slate-500">No results found</li>';
                        return;
                    }

                    data.forEach(item => {
                        const li = document.createElement('li');
                        li.className = 'p-3 text-sm text-slate-700 hover:bg-slate-100 cursor-pointer border-b border-slate-100 last:border-0';
                        li.textContent = item.display_name;
                        li.onclick = () => {
                            updateMapAndInputs(item.lat, item.lon, radiusInput.value, item.display_name);
                            searchResults.classList.add('hidden');
                            searchInput.value = item.display_name;
                        };
                        searchResults.appendChild(li);
                    });
                });
        }

        searchBtn.addEventListener('click', performSearch);
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                performSearch();
            }
        });

        // Current Location (Geolocation API)
        const currentLocBtn = document.getElementById('current-location-btn');
        currentLocBtn.addEventListener('click', () => {
            if (navigator.geolocation) {
                currentLocBtn.innerHTML = '<i data-lucide="loader" class="w-5 h-5 mr-1 animate-spin"></i> Locating...';
                
                // Re-initialize Lucide icons for the new HTML
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }

                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const lat = position.coords.latitude.toFixed(7);
                        const lng = position.coords.longitude.toFixed(7);
                        
                        // Reverse Geocode
                        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                            .then(res => res.json())
                            .then(data => {
                                const addr = data.display_name || '';
                                updateMapAndInputs(lat, lng, radiusInput.value, addr);
                                currentLocBtn.innerHTML = '<i data-lucide="crosshair" class="w-5 h-5 mr-1"></i> Current';
                                if (typeof lucide !== 'undefined') lucide.createIcons();
                            })
                            .catch(err => {
                                updateMapAndInputs(lat, lng, radiusInput.value);
                                currentLocBtn.innerHTML = '<i data-lucide="crosshair" class="w-5 h-5 mr-1"></i> Current';
                                if (typeof lucide !== 'undefined') lucide.createIcons();
                            });
                    },
                    (error) => {
                        alert("Unable to retrieve your location. Please ensure location services are allowed in your browser.");
                        currentLocBtn.innerHTML = '<i data-lucide="crosshair" class="w-5 h-5 mr-1"></i> Current';
                        if (typeof lucide !== 'undefined') lucide.createIcons();
                    },
                    { enableHighAccuracy: true }
                );
            } else {
                alert("Geolocation is not supported by this browser.");
            }
        });

        // Hide search results on outside click
        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.classList.add('hidden');
            }
        });
    });
</script>
@endsection
