<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OpenApi\Attributes as OA;

class AuthController extends AbstractController
{
    #[Route('/api/auth/register', name: 'api_register', methods: ['POST'])]
    #[OA\Post(
        path: '/api/auth/register',
        summary: 'Register a new user',
        tags: ['Authentication']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com', description: 'User email address (must be unique)'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'securePassword123', description: 'User password'),
                new OA\Property(property: 'name', type: 'string', example: 'John Doe', description: 'User full name (optional)', nullable: true)
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'User successfully registered',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'User registered successfully'),
                new OA\Property(
                    property: 'user',
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '60000a54-e220-4b17-95c3-ebdfa164caf9'),
                        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                        new OA\Property(property: 'name', type: 'string', example: 'John Doe', nullable: true),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-11-11 15:28:44')
                    ],
                    type: 'object'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad request - validation errors',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Validation failed'),
                new OA\Property(property: 'details', type: 'object')
            ]
        )
    )]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        JWTTokenManagerInterface $jwtManager
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email']) || !isset($data['password'])) {
            return $this->json([
                'error' => 'Email and password are required'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->json([
                'error' => 'Invalid email format'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setName($data['name'] ?? null);

        // Hash the password
        $hashedPassword = $passwordHasher->hashPassword(
            $user,
            $data['password']
        );
        $user->setPassword($hashedPassword);

        // Validate the user
        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json([
                'error' => 'Validation failed',
                'details' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $entityManager->persist($user);
            $entityManager->flush();

            // Generate JWT token for immediate login
            $token = $jwtManager->create($user);

            return $this->json([
                'message' => 'User registered successfully',
                'token' => $token,
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'name' => $user->getName(),
                    'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s')
                ]
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Registration failed',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/auth/login', name: 'api_login', methods: ['POST'])]
    #[OA\Post(
        path: '/api/auth/login',
        summary: 'User login',
        description: 'Authenticates a user and returns a JWT token',
        tags: ['Authentication']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com', description: 'User email address'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'securePassword123', description: 'User password')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Successfully authenticated',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...', description: 'JWT authentication token')
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized - invalid credentials',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'integer', example: 401),
                new OA\Property(property: 'message', type: 'string', example: 'Invalid credentials')
            ]
        )
    )]
    public function loginCheck(): JsonResponse
    {
        // This method can be blank - it will be intercepted by the json_login firewall
        return $this->json([
            'message' => 'Login endpoint'
        ]);
    }

    #[Route('/api/auth/me', name: 'api_me', methods: ['GET'])]
    #[OA\Get(
        path: '/api/auth/me',
        summary: 'Get current user information',
        description: 'Returns the authenticated user profile information',
        security: [['Bearer' => []]],
        tags: ['User']
    )]
    #[OA\Response(
        response: 200,
        description: 'User information retrieved successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'user',
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '60000a54-e220-4b17-95c3-ebdfa164caf9', description: 'Unique user identifier'),
                        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com', description: 'User email address'),
                        new OA\Property(property: 'name', type: 'string', example: 'John Doe', nullable: true, description: 'User full name'),
                        new OA\Property(
                            property: 'roles',
                            type: 'array',
                            items: new OA\Items(type: 'string'),
                            example: ['ROLE_USER'],
                            description: 'User roles'
                        ),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-11-11 15:28:44', description: 'Account creation timestamp')
                    ],
                    type: 'object'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized - missing or invalid token',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Not authenticated')
            ]
        )
    )]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json([
                'error' => 'Not authenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName(),
                'roles' => $user->getRoles(),
                'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s')
            ]
        ]);
    }
}
