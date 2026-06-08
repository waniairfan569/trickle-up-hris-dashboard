<?php

namespace App\Services;

use App\Models\User;
use App\Exceptions\GeofenceException;

class GeofenceService
{
    /**
     * Calculate distance in meters between two GPS coordinates using Haversine formula.
     */
    public function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $earthRadius = 6371000; // meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        
        $a = sin($dLat/2) * sin($dLat/2) + 
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
             sin($dLng/2) * sin($dLng/2);
             
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return (int)round($earthRadius * $c);
    }

    /**
     * Check if a user is within the radius of any of their assigned office locations.
     */
    public function isWithinAnyOffice(User $user, float $lat, float $lng): array
    {
        $locations = $user->officeLocations()->where('is_active', true)->get();
        
        if ($locations->isEmpty()) {
            throw new GeofenceException('No office location assigned to this employee. Contact HR.');
        }
        
        // Check for remote allowance
        foreach ($locations as $location) {
            if ($location->allow_remote) {
                return [
                    'allowed' => true, 
                    'location' => $location, 
                    'distance' => 0, 
                    'reason' => 'remote_allowed'
                ];
            }
        }
        
        $nearest_distance = null;
        $nearest_location = null;
        
        foreach ($locations as $location) {
            $distance = $this->calculateDistance($lat, $lng, $location->latitude, $location->longitude);
            
            if ($distance <= $location->radius_meters) {
                return [
                    'allowed' => true,
                    'location' => $location,
                    'distance' => $distance,
                    'reason' => 'within_radius'
                ];
            }
            
            if ($nearest_distance === null || $distance < $nearest_distance) {
                $nearest_distance = $distance;
                $nearest_location = $location;
            }
        }
        
        return [
            'allowed' => false,
            'location' => null,
            'distance' => $nearest_distance,
            'nearest_location' => $nearest_location,
            'reason' => 'outside_radius',
            'message' => "You are {$nearest_distance}m away from {$nearest_location->name}. You must be within {$nearest_location->radius_meters}m to clock in."
        ];
    }

    /**
     * Get frontend config for the clock widget.
     */
    public function getStatusForFrontend(User $user): array
    {
        $locations = $user->officeLocations()->where('is_active', true)->get();
        $geofence_enabled = false;
        
        if ($locations->isNotEmpty()) {
            // Geofence is enabled if there is at least one location and NONE of them allow remote
            $geofence_enabled = !$locations->contains('allow_remote', true);
        }
        
        return [
            'office_locations' => $locations->map(function ($loc) {
                return [
                    'id' => $loc->id,
                    'name' => $loc->name,
                    'lat' => $loc->latitude,
                    'lng' => $loc->longitude,
                    'radius' => $loc->radius_meters,
                    'allow_remote' => $loc->allow_remote
                ];
            })->toArray(),
            'geofence_enabled' => $geofence_enabled
        ];
    }
}
