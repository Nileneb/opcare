<?php

use App\Domains\Vision\Services\HttpVisionClient;
use Illuminate\Support\Facades\Http;

// Minimal valid MCP response with empty detections.
function mcpResponse(string $json): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => [
            'content' => [
                ['type' => 'text', 'text' => $json],
            ],
        ],
    ];
}

beforeEach(function () {
    config([
        'vision.fake' => false,
        'vision.url' => 'http://vision-mcp.test',
        'vision.token' => 'test-token',
    ]);
});

// ─── detect ───────────────────────────────────────────────────────────────────

it('detect sendet exakt image_base64 / model_path / confidence an MCP', function () {
    Http::fake([
        'vision-mcp.test/*' => Http::response(
            mcpResponse('{"detections":[],"counts":{},"model_used":"test"}'),
            200
        ),
    ]);

    $client = new HttpVisionClient;
    $client->detect('b64data==', '/models/v1.pt', 0.5);

    Http::assertSent(function ($request) {
        $args = $request->data()['params']['arguments'] ?? [];

        return $args['image_base64'] === 'b64data=='
            && $args['model_path'] === '/models/v1.pt'
            && $args['confidence'] === 0.5
            && ! array_key_exists('image_b64', $args)
            && ! array_key_exists('conf', $args);
    });
});

// ─── auto_annotate ────────────────────────────────────────────────────────────

it('autoAnnotate sendet exakt image_base64 / use_sam an MCP', function () {
    Http::fake([
        'vision-mcp.test/*' => Http::response(
            mcpResponse('{"suggestions":[]}'),
            200
        ),
    ]);

    $client = new HttpVisionClient;
    $client->autoAnnotate('imgb64==', false);

    Http::assertSent(function ($request) {
        $args = $request->data()['params']['arguments'] ?? [];

        return $args['image_base64'] === 'imgb64=='
            && $args['use_sam'] === false
            && ! array_key_exists('image_b64', $args);
    });
});

// ─── train ────────────────────────────────────────────────────────────────────

it('train sendet exakt dataset_zip_base64 / tenant_id an MCP', function () {
    Http::fake([
        'vision-mcp.test/*' => Http::response(
            mcpResponse('{"job_id":"job-abc-123"}'),
            200
        ),
    ]);

    $client = new HttpVisionClient;
    $jobId = $client->train('zipdata==', 'tenant-42');

    expect($jobId)->toBe('job-abc-123');

    Http::assertSent(function ($request) {
        $args = $request->data()['params']['arguments'] ?? [];

        return $args['dataset_zip_base64'] === 'zipdata=='
            && $args['tenant_id'] === 'tenant-42'
            && ! array_key_exists('zip_b64', $args)
            && ! array_key_exists('tenant_ref', $args);
    });
});

it('train mappt optionale Felder base_model / epochs / batch_size / image_size', function () {
    Http::fake([
        'vision-mcp.test/*' => Http::response(
            mcpResponse('{"job_id":"job-opts"}'),
            200
        ),
    ]);

    $client = new HttpVisionClient;
    $client->train('z==', 'tenant-1', [
        'base_model' => 'yolo11n.pt',
        'epochs' => 50,
        'batch_size' => 16,
        'image_size' => 640,
    ]);

    Http::assertSent(function ($request) {
        $args = $request->data()['params']['arguments'] ?? [];

        return $args['base_model'] === 'yolo11n.pt'
            && $args['epochs'] === 50
            && $args['batch_size'] === 16
            && $args['image_size'] === 640;
    });
});

// ─── train_status ─────────────────────────────────────────────────────────────

it('trainStatus sendet exakt job_id an MCP', function () {
    Http::fake([
        'vision-mcp.test/*' => Http::response(
            mcpResponse('{"status":"running","model_path":null}'),
            200
        ),
    ]);

    $client = new HttpVisionClient;
    $status = $client->trainStatus('job-xyz');

    expect($status['status'])->toBe('running');

    Http::assertSent(function ($request) {
        $args = $request->data()['params']['arguments'] ?? [];

        return $args['job_id'] === 'job-xyz'
            && $request->data()['params']['name'] === 'train_status';
    });
});
