<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Logger;
use App\Models\User;

class AuthController extends Controller
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    // ------------------------------------------------------------------
    // POST /auth/register
    // ------------------------------------------------------------------

    public function register(array $params = []): void
    {
        $body = $this->getBody();

        $errors = $this->validate($body, [
            'nom'      => 'required|min:2|max:100',
            'prenom'   => 'required|min:2|max:100',
            'email'    => 'required|email',
            'password' => 'required|min:8|max:255',
        ]);

        if (!empty($errors)) {
            $this->error('Validation failed.', 422, $errors);
            return;
        }

        // Vérifier l'unicité de l'email
        if ($this->userModel->emailExists($body['email'])) {
            $this->error('This email address is already in use.', 409);
            return;
        }

        // Rôle autorisé à l'inscription (empêcher de se créer admin)
        $role = in_array($body['role'] ?? '', ['client', 'vendeur'], true)
            ? $body['role']
            : 'client';

        try {
            $user = $this->userModel->create([
                'nom'       => $body['nom'],
                'prenom'    => $body['prenom'],
                'email'     => $body['email'],
                'password'  => $body['password'],
                'role'      => $role,
                'telephone' => $body['telephone'] ?? null,
            ]);

            if ($user === null) {
                $this->error('User creation failed.', 500);
                return;
            }

            $token = Auth::generateToken($user);

            Logger::info('User registered', ['user_id' => $user['id'], 'role' => $user['role']]);

            $this->json([
                'success'      => true,
                'message'      => 'Registration successful.',
                'access_token' => $token,
                'user'         => $user,
            ], 201);
        } catch (\Throwable $e) {
            $this->error('Registration failed: ' . $e->getMessage(), 500);
        }
    }

    // ------------------------------------------------------------------
    // POST /auth/login
    // ------------------------------------------------------------------

    public function login(array $params = []): void
    {
        $body = $this->getBody();

        $errors = $this->validate($body, [
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (!empty($errors)) {
            $this->error('Validation failed.', 422, $errors);
            return;
        }

        $user = $this->userModel->findByEmail($body['email']);

        if ($user === null || !$this->userModel->verifyPassword($body['password'], $user['password_hash'])) {
            Logger::warning('Failed login attempt', ['email' => $body['email']]);
            // Message générique pour éviter l'énumération d'emails
            $this->error('Invalid email or password.', 401);
            return;
        }

        $userPublic = $this->userModel->toArray($user);
        $token      = Auth::generateToken($userPublic);

        Logger::info('User logged in', ['user_id' => $userPublic['id'], 'role' => $userPublic['role']]);

        $this->json([
            'success'      => true,
            'message'      => 'Login successful.',
            'access_token' => $token,
            'user'         => $userPublic,
        ]);
    }

    // ------------------------------------------------------------------
    // GET /auth/me  (JWT required)
    // ------------------------------------------------------------------

    public function me(array $params = []): void
    {
        $authPayload = Auth::getCurrentUser();

        if ($authPayload === null) {
            $this->error('Unauthorized.', 401);
            return;
        }

        $user = $this->userModel->findById((int) $authPayload['sub']);

        if ($user === null) {
            $this->error('User not found.', 404);
            return;
        }

        $this->success($user);
    }

    // ------------------------------------------------------------------
    // PUT /auth/me  (JWT required)
    // ------------------------------------------------------------------

    public function updateMe(array $params = []): void
    {
        $authPayload = Auth::getCurrentUser();

        if ($authPayload === null) {
            $this->error('Unauthorized.', 401);
            return;
        }

        $body   = $this->getBody();
        $userId = (int) $authPayload['sub'];

        $errors = $this->validate($body, [
            'nom'    => 'min:2|max:100',
            'prenom' => 'min:2|max:100',
        ]);

        if (!empty($errors)) {
            $this->error('Validation failed.', 422, $errors);
            return;
        }

        // Si changement de mot de passe, l'ancien est requis
        if (!empty($body['password'])) {
            if (empty($body['current_password'])) {
                $this->error('Current password is required to set a new one.', 422);
                return;
            }

            $currentUser = $this->userModel->findByEmail($authPayload['email']);
            if ($currentUser === null || !$this->userModel->verifyPassword($body['current_password'], $currentUser['password_hash'])) {
                $this->error('Current password is incorrect.', 403);
                return;
            }
        }

        $user = $this->userModel->update($userId, $body);

        if ($user === null) {
            $this->error('Update failed.', 500);
            return;
        }

        $this->success($user, 'Profile updated successfully.');
    }
}
