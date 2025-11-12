<?php

declare(strict_types=1);

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\OpenApi;
use ApiPlatform\OpenApi\Model\Tag;

/**
 * OpenAPI decorator for Monad quest endpoints
 */
readonly class QuestDecorator implements OpenApiFactoryInterface
{
    public function __construct(private OpenApiFactoryInterface $decorated)
    {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);
        $paths = $openApi->getPaths();

        // Add /api/quests GET - List quests with filtering
        $questsListPath = new PathItem(
            get: new Operation(
                tags: ['Quest'],
                summary: 'Get list of quests',
                description: 'Returns a list of quests filtered by status. By default returns active quests (available_from < now < available_to).',
                parameters: [
                    new Parameter(
                        name: 'status',
                        in: 'query',
                        description: 'Filter quests by status: active (default) or expired',
                        required: false,
                        schema: [
                            'type' => 'string',
                            'enum' => ['active', 'expired'],
                            'default' => 'active',
                        ]
                    ),
                ],
                responses: [
                    '200' => new Response(
                        description: 'List of quests retrieved successfully',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(
                                schema: new \ArrayObject([
                                    'type' => 'object',
                                    'properties' => [
                                        'quests' => [
                                            'type' => 'array',
                                            'items' => [
                                                'type' => 'object',
                                                'properties' => [
                                                    'id' => [
                                                        'type' => 'string',
                                                        'format' => 'uuid',
                                                        'example' => '60000a54-e220-4b17-95c3-ebdfa164caf9',
                                                        'description' => 'Quest unique identifier',
                                                    ],
                                                    'name' => [
                                                        'type' => 'string',
                                                        'example' => 'Campus Discovery Tour',
                                                        'description' => 'Quest name',
                                                    ],
                                                    'description' => [
                                                        'type' => 'string',
                                                        'example' => 'Explore the main campus buildings and learn about university history',
                                                        'description' => 'Quest description',
                                                    ],
                                                    'points' => [
                                                        'type' => 'number',
                                                        'format' => 'float',
                                                        'example' => 100.0,
                                                        'description' => 'Points awarded for completing this quest',
                                                    ],
                                                    'estimatedDuration' => [
                                                        'type' => 'integer',
                                                        'nullable' => true,
                                                        'example' => 30,
                                                        'description' => 'Estimated duration in minutes',
                                                    ],
                                                    'numberOfSteps' => [
                                                        'type' => 'integer',
                                                        'example' => 5,
                                                        'description' => 'Number of steps in this quest',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ])
                            ),
                        ])
                    ),
                    '400' => new Response(
                        description: 'Bad request - invalid status parameter',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(
                                schema: new \ArrayObject([
                                    'type' => 'object',
                                    'properties' => [
                                        'error' => [
                                            'type' => 'string',
                                            'example' => 'Invalid status parameter. Allowed values: active, expired',
                                        ],
                                    ],
                                ])
                            ),
                        ])
                    ),
                ],
                security: []
            )
        );
        $paths->addPath('/api/quests', $questsListPath);

        // Add /api/quest/{id} GET - Get quest details
        $questDetailPath = new PathItem(
            get: new Operation(
                tags: ['Quest'],
                summary: 'Get quest detail',
                description: 'Returns full quest details including all steps. Does not require authentication.',
                parameters: [
                    new Parameter(
                        name: 'id',
                        in: 'path',
                        description: 'Quest UUID',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                            'example' => '60000a54-e220-4b17-95c3-ebdfa164caf9',
                        ]
                    ),
                ],
                responses: [
                    '200' => new Response(
                        description: 'Quest details retrieved successfully',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(
                                schema: new \ArrayObject([
                                    'type' => 'object',
                                    'properties' => [
                                        'id' => [
                                            'type' => 'string',
                                            'format' => 'uuid',
                                            'example' => '60000a54-e220-4b17-95c3-ebdfa164caf9',
                                            'description' => 'Quest unique identifier',
                                        ],
                                        'name' => [
                                            'type' => 'string',
                                            'example' => 'Campus Discovery',
                                            'description' => 'Quest name',
                                        ],
                                        'description' => [
                                            'type' => 'string',
                                            'example' => 'Explore the campus and discover hidden locations',
                                            'description' => 'Quest description',
                                        ],
                                        'points' => [
                                            'type' => 'number',
                                            'format' => 'float',
                                            'example' => 100.0,
                                            'description' => 'Points awarded for completion',
                                        ],
                                        'estimatedDuration' => [
                                            'type' => 'integer',
                                            'nullable' => true,
                                            'example' => 30,
                                            'description' => 'Estimated duration in minutes',
                                        ],
                                        'createdAt' => [
                                            'type' => 'string',
                                            'format' => 'date-time',
                                            'example' => '2025-11-11 15:28:44',
                                            'description' => 'Quest creation timestamp',
                                        ],
                                        'steps' => [
                                            'type' => 'array',
                                            'description' => 'Quest steps ordered by sequence',
                                            'items' => [
                                                'type' => 'object',
                                                'properties' => [
                                                    'id' => [
                                                        'type' => 'string',
                                                        'format' => 'uuid',
                                                        'example' => '70000a54-e220-4b17-95c3-ebdfa164caf9',
                                                        'description' => 'Step unique identifier',
                                                    ],
                                                    'name' => [
                                                        'type' => 'string',
                                                        'example' => 'Scan QR Code at Library',
                                                        'description' => 'Step name',
                                                    ],
                                                    'type' => [
                                                        'type' => 'string',
                                                        'enum' => ['start', 'wait', 'scan_qr', 'connect_to_ap', 'walk_to', 'find_ble_device', 'finish'],
                                                        'example' => 'scan_qr',
                                                        'description' => 'Step type',
                                                    ],
                                                    'order' => [
                                                        'type' => 'integer',
                                                        'example' => 1,
                                                        'description' => 'Step order in quest sequence',
                                                    ],
                                                    'config' => [
                                                        'type' => 'object',
                                                        'example' => ['qr_code_id' => 'abc123'],
                                                        'description' => 'Step-specific configuration',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ])
                            ),
                        ])
                    ),
                    '400' => new Response(
                        description: 'Invalid UUID format',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(
                                schema: new \ArrayObject([
                                    'type' => 'object',
                                    'properties' => [
                                        'error' => [
                                            'type' => 'string',
                                            'example' => 'Invalid quest ID format',
                                        ],
                                    ],
                                ])
                            ),
                        ])
                    ),
                    '404' => new Response(
                        description: 'Quest not found',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(
                                schema: new \ArrayObject([
                                    'type' => 'object',
                                    'properties' => [
                                        'error' => [
                                            'type' => 'string',
                                            'example' => 'Quest not found',
                                        ],
                                    ],
                                ])
                            ),
                        ])
                    ),
                ],
                security: []
            )
        );
        $paths->addPath('/api/quest/{id}', $questDetailPath);

        // Add /api/quest/{id}/start POST - Start a quest (requires JWT auth)
        $questStartPath = new PathItem(
            post: new Operation(
                tags: ['Quest'],
                summary: 'Start a quest',
                description: 'Creates a quest enrollment for the authenticated user and initializes all quest step completions',
                parameters: [
                    new Parameter(
                        name: 'id',
                        in: 'path',
                        description: 'Quest UUID',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                            'example' => '60000a54-e220-4b17-95c3-ebdfa164caf9',
                        ]
                    ),
                ],
                responses: [
                    '200' => new Response(
                        description: 'Quest started successfully',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(
                                schema: new \ArrayObject([
                                    'type' => 'object',
                                    'properties' => [
                                        'enrollment_id' => [
                                            'type' => 'string',
                                            'format' => 'uuid',
                                            'example' => '70000b64-f330-5c27-a6d4-fceegb275db0',
                                            'description' => 'Quest enrollment UUID',
                                        ],
                                        'quest' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'id' => [
                                                    'type' => 'string',
                                                    'format' => 'uuid',
                                                    'example' => '60000a54-e220-4b17-95c3-ebdfa164caf9',
                                                ],
                                                'name' => [
                                                    'type' => 'string',
                                                    'example' => 'City Explorer Quest',
                                                ],
                                                'description' => [
                                                    'type' => 'string',
                                                    'example' => 'Explore the city and discover hidden gems',
                                                ],
                                                'steps' => [
                                                    'type' => 'array',
                                                    'items' => [
                                                        'type' => 'object',
                                                        'properties' => [
                                                            'step_id' => [
                                                                'type' => 'string',
                                                                'format' => 'uuid',
                                                                'description' => 'Quest step identifier',
                                                            ],
                                                            'step_completion_id' => [
                                                                'type' => 'string',
                                                                'format' => 'uuid',
                                                                'description' => 'Step completion record identifier',
                                                            ],
                                                            'name' => [
                                                                'type' => 'string',
                                                                'description' => 'Step name',
                                                            ],
                                                            'type' => [
                                                                'type' => 'string',
                                                                'enum' => ['start', 'wait', 'scan_qr', 'connect_to_ap', 'walk_to', 'find_ble_device', 'finish'],
                                                                'description' => 'Step type',
                                                            ],
                                                            'order' => [
                                                                'type' => 'integer',
                                                                'description' => 'Step sequence order',
                                                            ],
                                                            'config' => [
                                                                'type' => 'object',
                                                                'description' => 'Step-specific configuration',
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                        'data_path' => [
                                            'type' => 'string',
                                            'example' => 's3://monad-bucket/experiments/2025/11/11/60000a54-e220-4b17-95c3-ebdfa164caf9/70000b64-f330-5c27-a6d4-fceegb275db0/',
                                            'description' => 'S3 data storage path for quest data',
                                        ],
                                        'started_at' => [
                                            'type' => 'string',
                                            'format' => 'date-time',
                                            'example' => '2025-11-11T15:28:44Z',
                                            'description' => 'Quest start timestamp',
                                        ],
                                    ],
                                ])
                            ),
                        ])
                    ),
                    '400' => new Response(
                        description: 'Bad request - quest is not active or invalid',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(
                                schema: new \ArrayObject([
                                    'type' => 'object',
                                    'properties' => [
                                        'error' => [
                                            'type' => 'string',
                                            'example' => 'Quest is not currently active',
                                        ],
                                    ],
                                ])
                            ),
                        ])
                    ),
                    '401' => new Response(
                        description: 'Unauthorized - not authenticated',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(
                                schema: new \ArrayObject([
                                    'type' => 'object',
                                    'properties' => [
                                        'error' => [
                                            'type' => 'string',
                                            'example' => 'Authentication required',
                                        ],
                                    ],
                                ])
                            ),
                        ])
                    ),
                    '404' => new Response(
                        description: 'Quest not found',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(
                                schema: new \ArrayObject([
                                    'type' => 'object',
                                    'properties' => [
                                        'error' => [
                                            'type' => 'string',
                                            'example' => 'Quest not found',
                                        ],
                                    ],
                                ])
                            ),
                        ])
                    ),
                    '409' => new Response(
                        description: 'Conflict - user already enrolled in this quest',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(
                                schema: new \ArrayObject([
                                    'type' => 'object',
                                    'properties' => [
                                        'error' => [
                                            'type' => 'string',
                                            'example' => 'You are already enrolled in this quest',
                                        ],
                                    ],
                                ])
                            ),
                        ])
                    ),
                ],
                security: [['Bearer' => []]]
            )
        );
        $paths->addPath('/api/quest/{id}/start', $questStartPath);

        // Add /api/quest/{quest_id}/complete POST - Complete a quest (requires JWT auth)
        $questCompletePath = new PathItem(
            post: new Operation(
                tags: ['Quest'],
                summary: 'Complete a quest',
                description: 'Receives bulk data from device after quest completion and updates enrollment and step completions',
                parameters: [
                    new Parameter(
                        name: 'quest_id',
                        in: 'path',
                        description: 'Quest UUID',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                        ]
                    ),
                ],
                requestBody: new RequestBody(
                    description: 'Quest completion data including all step results',
                    required: true,
                    content: new \ArrayObject([
                        'application/json' => new MediaType(
                            schema: new \ArrayObject([
                                'type' => 'object',
                                'required' => ['enrollment_id', 'completed_at', 'steps'],
                                'properties' => [
                                    'enrollment_id' => [
                                        'type' => 'string',
                                        'format' => 'uuid',
                                        'example' => '60000a54-e220-4b17-95c3-ebdfa164caf9',
                                        'description' => 'Quest enrollment UUID',
                                    ],
                                    'completed_at' => [
                                        'type' => 'string',
                                        'format' => 'date-time',
                                        'example' => '2025-11-11T15:30:00Z',
                                        'description' => 'Quest completion timestamp',
                                    ],
                                    'steps' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'object',
                                            'required' => ['step_completion_id', 'status', 'started_at', 'completed_at'],
                                            'properties' => [
                                                'step_completion_id' => [
                                                    'type' => 'string',
                                                    'format' => 'uuid',
                                                    'description' => 'Step completion record UUID',
                                                ],
                                                'status' => [
                                                    'type' => 'string',
                                                    'enum' => ['completed', 'failed', 'skipped'],
                                                    'description' => 'Step completion status',
                                                ],
                                                'started_at' => [
                                                    'type' => 'string',
                                                    'format' => 'date-time',
                                                    'description' => 'Step start timestamp',
                                                ],
                                                'completed_at' => [
                                                    'type' => 'string',
                                                    'format' => 'date-time',
                                                    'description' => 'Step completion timestamp',
                                                ],
                                                'step_data' => [
                                                    'type' => 'object',
                                                    'nullable' => true,
                                                    'description' => 'Step-specific data collected during execution',
                                                ],
                                                'skip_record' => [
                                                    'type' => 'object',
                                                    'nullable' => true,
                                                    'description' => 'Required for failed/skipped steps',
                                                    'properties' => [
                                                        'message' => [
                                                            'type' => 'string',
                                                            'description' => 'Reason for skipping/failing',
                                                        ],
                                                        'error_code' => [
                                                            'type' => 'string',
                                                            'nullable' => true,
                                                            'description' => 'Optional error code',
                                                        ],
                                                        'metadata' => [
                                                            'type' => 'object',
                                                            'nullable' => true,
                                                            'description' => 'Additional metadata',
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                    'data_file' => [
                                        'type' => 'object',
                                        'nullable' => true,
                                        'description' => 'Optional file metadata if data file was uploaded',
                                        'properties' => [
                                            'filename' => [
                                                'type' => 'string',
                                                'description' => 'Name of uploaded file',
                                            ],
                                            'size' => [
                                                'type' => 'number',
                                                'description' => 'File size in bytes',
                                            ],
                                            'checksum' => [
                                                'type' => 'string',
                                                'description' => 'File checksum for validation',
                                            ],
                                        ],
                                    ],
                                ],
                            ])
                        ),
                    ])
                ),
                responses: [
                    '200' => new Response(
                        description: 'Quest completed successfully',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(
                                schema: new \ArrayObject([
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => [
                                            'type' => 'boolean',
                                            'example' => true,
                                            'description' => 'Whether quest was completed successfully',
                                        ],
                                        'enrollment_id' => [
                                            'type' => 'string',
                                            'format' => 'uuid',
                                            'description' => 'Quest enrollment UUID',
                                        ],
                                        'points_earned' => [
                                            'type' => 'number',
                                            'example' => 100,
                                            'description' => 'Points earned for completion',
                                        ],
                                        'completed_at' => [
                                            'type' => 'string',
                                            'format' => 'date-time',
                                            'description' => 'Quest completion timestamp',
                                        ],
                                    ],
                                ])
                            ),
                        ])
                    ),
                    '400' => new Response(
                        description: 'Bad request - invalid data structure or validation errors',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(
                                schema: new \ArrayObject([
                                    'type' => 'object',
                                    'properties' => [
                                        'error' => [
                                            'type' => 'string',
                                            'example' => 'Validation failed',
                                        ],
                                        'details' => [
                                            'type' => 'object',
                                            'nullable' => true,
                                            'description' => 'Validation error details',
                                        ],
                                    ],
                                ])
                            ),
                        ])
                    ),
                    '401' => new Response(
                        description: 'Unauthorized - not authenticated',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(
                                schema: new \ArrayObject([
                                    'type' => 'object',
                                    'properties' => [
                                        'error' => [
                                            'type' => 'string',
                                            'example' => 'Not authenticated',
                                        ],
                                    ],
                                ])
                            ),
                        ])
                    ),
                    '403' => new Response(
                        description: 'Forbidden - enrollment does not belong to authenticated user',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(
                                schema: new \ArrayObject([
                                    'type' => 'object',
                                    'properties' => [
                                        'error' => [
                                            'type' => 'string',
                                            'example' => 'This enrollment does not belong to you',
                                        ],
                                    ],
                                ])
                            ),
                        ])
                    ),
                    '404' => new Response(
                        description: 'Enrollment not found',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(
                                schema: new \ArrayObject([
                                    'type' => 'object',
                                    'properties' => [
                                        'error' => [
                                            'type' => 'string',
                                            'example' => 'Enrollment not found',
                                        ],
                                    ],
                                ])
                            ),
                        ])
                    ),
                    '409' => new Response(
                        description: 'Conflict - enrollment already completed or abandoned',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(
                                schema: new \ArrayObject([
                                    'type' => 'object',
                                    'properties' => [
                                        'error' => [
                                            'type' => 'string',
                                            'example' => 'Enrollment is already completed',
                                        ],
                                    ],
                                ])
                            ),
                        ])
                    ),
                ],
                security: [['Bearer' => []]]
            )
        );
        $paths->addPath('/api/quest/{quest_id}/complete', $questCompletePath);

        // Ensure Quest tag exists
        $rootTags = $openApi->getTags();
        $hasQuestTag = false;

        foreach ($rootTags as $tag) {
            if ($tag->getName() === 'Quest') {
                $hasQuestTag = true;
                break;
            }
        }

        if (!$hasQuestTag) {
            $newTags = $rootTags;
            $newTags[] = new Tag('Quest', 'Quest management and enrollment endpoints');
            $openApi = $openApi->withTags($newTags);
        }

        return $openApi;
    }
}
