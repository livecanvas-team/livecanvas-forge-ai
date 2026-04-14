<?php

defined('ABSPATH') || exit;

class LCFA_Design_System_Build_Gateway {
    private LCFA_Local_MCP_Bridge $local_mcp_bridge;

    public function __construct(LCFA_Local_MCP_Bridge $local_mcp_bridge) {
        $this->local_mcp_bridge = $local_mcp_bridge;
    }

    public function get_status(): array {
        return $this->local_mcp_bridge->get_status();
    }

    public function build_windpress_cache(array $arguments = []): array {
        return $this->local_mcp_bridge->build_windpress_cache($arguments);
    }
}
