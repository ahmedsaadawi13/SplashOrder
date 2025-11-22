<?php
// FILE: /app/services/RealtimeService.php

namespace App\Services;

/**
 * Real-time Service
 * Handles real-time updates using Pusher or WebSockets
 * For production, install Pusher: composer require pusher/pusher-php-server
 */
class RealtimeService
{
    private $pusher_key;
    private $pusher_secret;
    private $pusher_app_id;
    private $pusher_cluster;
    private $pusher;
    private $enabled;

    public function __construct()
    {
        $this->pusher_key = $_ENV['PUSHER_APP_KEY'] ?? '';
        $this->pusher_secret = $_ENV['PUSHER_APP_SECRET'] ?? '';
        $this->pusher_app_id = $_ENV['PUSHER_APP_ID'] ?? '';
        $this->pusher_cluster = $_ENV['PUSHER_APP_CLUSTER'] ?? 'us2';
        $this->enabled = !empty($this->pusher_key) && !empty($this->pusher_secret);

        if ($this->enabled && class_exists('\Pusher\Pusher')) {
            $this->pusher = new \Pusher\Pusher(
                $this->pusher_key,
                $this->pusher_secret,
                $this->pusher_app_id,
                ['cluster' => $this->pusher_cluster]
            );
        }
    }

    /**
     * Broadcast order status update
     *
     * @param int $tenant_id Tenant ID
     * @param int $order_id Order ID
     * @param string $status New status
     * @param array $order_data Order data
     * @return bool
     */
    public function broadcastOrderUpdate($tenant_id, $order_id, $status, $order_data = [])
    {
        $channel = "tenant.{$tenant_id}.orders";
        $event = 'order.updated';

        $data = [
            'order_id' => $order_id,
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
            'data' => $order_data
        ];

        return $this->trigger($channel, $event, $data);
    }

    /**
     * Broadcast new order notification
     *
     * @param int $tenant_id Tenant ID
     * @param array $order Order data
     * @return bool
     */
    public function broadcastNewOrder($tenant_id, $order)
    {
        $channel = "tenant.{$tenant_id}.orders";
        $event = 'order.created';

        return $this->trigger($channel, $event, $order);
    }

    /**
     * Broadcast driver location update
     *
     * @param int $order_id Order ID
     * @param float $latitude Latitude
     * @param float $longitude Longitude
     * @return bool
     */
    public function broadcastDriverLocation($order_id, $latitude, $longitude)
    {
        $channel = "order.{$order_id}.tracking";
        $event = 'driver.location';

        $data = [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        return $this->trigger($channel, $event, $data);
    }

    /**
     * Trigger event on channel
     *
     * @param string $channel Channel name
     * @param string $event Event name
     * @param array $data Event data
     * @return bool
     */
    private function trigger($channel, $event, $data)
    {
        if (!$this->enabled || !$this->pusher) {
            // Log for development
            error_log("Realtime event: {$channel} -> {$event} -> " . json_encode($data));
            return true;
        }

        try {
            $this->pusher->trigger($channel, $event, $data);
            return true;
        } catch (\Exception $e) {
            error_log("Pusher trigger failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get auth endpoint for private channels
     *
     * @param string $channel_name Channel name
     * @param string $socket_id Socket ID
     * @return string
     */
    public function authorizeChannel($channel_name, $socket_id)
    {
        if (!$this->enabled || !$this->pusher) {
            return json_encode(['auth' => '']);
        }

        return $this->pusher->socket_auth($channel_name, $socket_id);
    }
}
