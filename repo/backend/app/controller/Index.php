<?php

namespace app\controller;

use think\Response;

class Index
{
    /**
     * Health check endpoint
     * GET /api/v1/ping
     */
    public function ping(): Response
    {
        return json([
            'success' => true,
            'message' => 'CampusOps alive',
            'timestamp' => date('m/d/Y h:i:s A'),
        ]);
    }
}
