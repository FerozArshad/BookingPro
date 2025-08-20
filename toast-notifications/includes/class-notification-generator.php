<?php
/**
 * Notification Generator for BookingPro Toast System
 * 
 * Generates realistic notifications using actual plugin data
 * including services, ZIP codes, companies, and customer info.
 */

if (!defined('ABSPATH')) exit;

class BSP_Toast_Notification_Generator {
    
    private $db;
    private $location_service;
    private $stats_generator;
    
    public function __construct($db) {
        $this->db = $db;
        $this->location_service = new BSP_Toast_Location_Service();
        $this->stats_generator = new BSP_Toast_Statistics_Generator($db);
    }
    
    /**
     * Generate a random notification
     */
    public function get_random_notifications($count = 1) {
        $notifications = [];
        $types = ['booking_created', 'booking_confirmed', 'popular_service', 'customer_review', 'location_activity', 'company_achievement'];
        
        for ($i = 0; $i < $count; $i++) {
            $type = $types[array_rand($types)];
            $notifications[] = $this->generate_notification($type);
        }
        
        return $notifications;
    }
    
    /**
     * Generate notification by type
     */
    public function generate_notification($type) {
        switch ($type) {
            case 'booking_created':
                return $this->generate_booking_created();
            case 'booking_confirmed':
                return $this->generate_booking_confirmed();
            case 'popular_service':
                return $this->generate_popular_service();
            case 'customer_review':
                return $this->generate_customer_review();
            case 'location_activity':
                return $this->generate_location_activity();
            case 'company_achievement':
                return $this->generate_company_achievement();
            default:
                return $this->generate_booking_created();
        }
    }
    
    /**
     * Generate booking created notification
     */
    private function generate_booking_created() {
        $services = ['Roof', 'Windows', 'Bathroom', 'Siding', 'Kitchen'];
        $service = $services[array_rand($services)];
        
        $companies = $this->get_companies();
        $company = $companies[array_rand($companies)];
        
        $location = $this->location_service->get_random_location();
        $customer_name = $this->generate_customer_name();
        
        return [
            'type' => 'booking_created',
            'icon' => '📅',
            'color' => '#4CAF50',
            'title' => 'New Booking',
            'message' => "{$customer_name} booked {$service} service in {$location['city']}, {$location['state']}",
            'timestamp' => current_time('timestamp'),
            'duration' => 5000,
            'data' => [
                'service' => $service,
                'company' => $company,
                'location' => $location,
                'customer' => $customer_name
            ]
        ];
    }
    
    /**
     * Generate booking confirmed notification
     */
    private function generate_booking_confirmed() {
        $services = ['Roof', 'Windows', 'Bathroom', 'Siding', 'Kitchen'];
        $service = $services[array_rand($services)];
        
        $companies = $this->get_companies();
        $company = $companies[array_rand($companies)];
        
        $customer_name = $this->generate_customer_name();
        
        return [
            'type' => 'booking_confirmed',
            'icon' => '✅',
            'color' => '#2196F3',
            'title' => 'Booking Confirmed',
            'message' => "{$company} confirmed {$service} appointment with {$customer_name}",
            'timestamp' => current_time('timestamp'),
            'duration' => 4000,
            'data' => [
                'service' => $service,
                'company' => $company,
                'customer' => $customer_name
            ]
        ];
    }
    
    /**
     * Generate popular service notification
     */
    private function generate_popular_service() {
        $services = ['Roof', 'Windows', 'Bathroom', 'Siding', 'Kitchen'];
        $service = $services[array_rand($services)];
        
        $stats = $this->stats_generator->get_service_stats($service);
        $location = $this->location_service->get_random_location();
        
        return [
            'type' => 'popular_service',
            'icon' => '🔥',
            'color' => '#FF9800',
            'title' => 'Trending Service',
            'message' => "{$service} is trending in {$location['city']} - {$stats['bookings']} bookings this week",
            'timestamp' => current_time('timestamp'),
            'duration' => 6000,
            'data' => [
                'service' => $service,
                'location' => $location,
                'stats' => $stats
            ]
        ];
    }
    
    /**
     * Generate customer review notification
     */
    private function generate_customer_review() {
        $companies = $this->get_companies();
        $company = $companies[array_rand($companies)];
        
        $services = ['Roof', 'Windows', 'Bathroom', 'Siding', 'Kitchen'];
        $service = $services[array_rand($services)];
        
        $ratings = [4.5, 4.7, 4.8, 4.9, 5.0];
        $rating = $ratings[array_rand($ratings)];
        
        $reviews = [
            'Excellent work and professional service!',
            'Very satisfied with the quality and timing.',
            'Highly recommend for anyone looking for quality work.',
            'Outstanding service and attention to detail.',
            'Great experience from start to finish!'
        ];
        
        $review = $reviews[array_rand($reviews)];
        $customer_name = $this->generate_customer_name();
        
        return [
            'type' => 'customer_review',
            'icon' => '⭐',
            'color' => '#FFC107',
            'title' => 'New Review',
            'message' => "{$customer_name} rated {$company} {$rating}/5 for {$service} service",
            'timestamp' => current_time('timestamp'),
            'duration' => 7000,
            'data' => [
                'company' => $company,
                'service' => $service,
                'rating' => $rating,
                'review' => $review,
                'customer' => $customer_name
            ]
        ];
    }
    
    /**
     * Generate location activity notification
     */
    private function generate_location_activity() {
        $location = $this->location_service->get_random_location();
        $stats = $this->stats_generator->get_location_stats($location['zip']);
        
        $activities = [
            'High booking activity',
            'Popular service area',
            'New service coverage',
            'Increased demand'
        ];
        
        $activity = $activities[array_rand($activities)];
        
        return [
            'type' => 'location_activity',
            'icon' => '🗺️',
            'color' => '#9C27B0',
            'title' => 'Location Update',
            'message' => "{$activity} in {$location['city']}, {$location['state']} - {$stats['bookings']} bookings",
            'timestamp' => current_time('timestamp'),
            'duration' => 5000,
            'data' => [
                'location' => $location,
                'activity' => $activity,
                'stats' => $stats
            ]
        ];
    }
    
    /**
     * Generate company achievement notification
     */
    private function generate_company_achievement() {
        $companies = $this->get_companies();
        $company = $companies[array_rand($companies)];
        
        $achievements = [
            'reached 100 completed projects',
            'earned 5-star rating average',
            'completed 50 bookings this month',
            'achieved 98% customer satisfaction',
            'expanded service area coverage'
        ];
        
        $achievement = $achievements[array_rand($achievements)];
        
        return [
            'type' => 'company_achievement',
            'icon' => '🏆',
            'color' => '#795548',
            'title' => 'Company Achievement',
            'message' => "{$company} {$achievement}",
            'timestamp' => current_time('timestamp'),
            'duration' => 6000,
            'data' => [
                'company' => $company,
                'achievement' => $achievement
            ]
        ];
    }
    
    /**
     * Get companies from plugin data
     */
    private function get_companies() {
        $companies = BSP_Database_Unified::get_companies();
        $names = [];
        
        foreach ($companies as $company) {
            $names[] = $company->name;
        }
        
        // Fallback if no companies found
        if (empty($names)) {
            $names = ['Top Remodeling Pro', 'Home Improvement Experts', 'Pro Remodeling Solutions'];
        }
        
        return $names;
    }
    
    /**
     * Generate random customer name
     */
    private function generate_customer_name() {
        $first_names = [
            'Alex', 'Sarah', 'Michael', 'Jessica', 'David', 'Emily', 'Chris', 'Ashley',
            'James', 'Amanda', 'Robert', 'Michelle', 'John', 'Lisa', 'Daniel', 'Jennifer',
            'Matthew', 'Rebecca', 'Andrew', 'Nicole', 'Joshua', 'Rachel', 'Ryan', 'Stephanie'
        ];
        
        $last_names = [
            'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez',
            'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas', 'Taylor',
            'Moore', 'Jackson', 'Martin', 'Lee', 'Perez', 'Thompson', 'White', 'Harris'
        ];
        
        $first = $first_names[array_rand($first_names)];
        $last = $last_names[array_rand($last_names)];
        
        return "{$first} {$last}";
    }
    
    /**
     * Get notification for specific booking
     */
    public function get_booking_notification($booking_id) {
        $booking = get_post($booking_id);
        
        if (!$booking || $booking->post_type !== 'bsp_booking') {
            return null;
        }
        
        $customer_name = get_post_meta($booking_id, '_customer_name', true);
        $service = get_post_meta($booking_id, '_service_type', true);
        $company = get_post_meta($booking_id, '_company_name', true);
        $zip_code = get_post_meta($booking_id, '_zip_code', true);
        
        $location = $this->location_service->get_location_by_zip($zip_code);
        
        return [
            'type' => 'booking_created',
            'icon' => '📅',
            'color' => '#4CAF50',
            'title' => 'New Booking',
            'message' => "{$customer_name} booked {$service} service in {$location['city']}, {$location['state']}",
            'timestamp' => current_time('timestamp'),
            'duration' => 5000,
            'data' => [
                'booking_id' => $booking_id,
                'service' => $service,
                'company' => $company,
                'location' => $location,
                'customer' => $customer_name
            ]
        ];
    }
}
