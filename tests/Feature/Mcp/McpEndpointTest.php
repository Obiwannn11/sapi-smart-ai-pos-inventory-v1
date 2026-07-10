<?php

test('the mcp business endpoint rejects unauthenticated requests', function () {
    $this->postJson('/mcp/business', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])->assertUnauthorized();
});
