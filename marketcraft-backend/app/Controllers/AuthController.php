<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
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

            $this->json([
                'success' => true,
                'message' => 'Registration successful.',
                'token'   => $token,
                'user'    => $user,
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

        $email = strtolower(trim($body['email']));
        $ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // ── 1. Récupérer l'utilisateur ─────────────────────────────────────
        $user = $this->userModel->findByEmailRaw($email);

        // ── 2. Vérifier le verrou brute-force ──────────────────────────────
        if ($user !== null && !empty($user['locked_until'])) {
            $lockedUntil = new \DateTime($user['locked_until']);
            if ($lockedUntil > new \DateTime()) {
                $remaining = $lockedUntil->diff(new \DateTime());
                $minutes   = $remaining->i + ($remaining->h * 60);
                $this->logActivity(null, 'login_locked', null, null, $ip, $email);
                $this->error(
                    "Compte temporairement bloqué. Réessayez dans {$minutes} minute(s).",
                    429
                );
                return;
            }
        }

        // ── 3. Vérifier les identifiants ───────────────────────────────────
        $passwordOk = $user !== null && $this->userModel->verifyPassword($body['password'], $user['password_hash']);

        if (!$passwordOk) {
            // Incrémenter les tentatives si l'utilisateur existe
            if ($user !== null) {
                $attempts = ($user['login_attempts'] ?? 0) + 1;
                if ($attempts >= 5) {
                    // Verrouiller 15 minutes
                    $this->userModel->lockAccount((int) $user['id'], 15);
                    $this->logActivity($user['id'], 'login_locked_trigger', null, null, $ip, $email);
                    $this->error('Trop de tentatives. Compte bloqué 15 minutes.', 429);
                } else {
                    $this->userModel->incrementLoginAttempts((int) $user['id']);
                    $remaining = 5 - $attempts;
                    $this->logActivity($user['id'], 'login_failed', null, null, $ip, $email);
                    $this->error("Identifiants invalides. {$remaining} tentative(s) restante(s).", 401);
                }
            } else {
                $this->logActivity(null, 'login_unknown_email', null, null, $ip, $email);
                $this->error('Invalid email or password.', 401);
            }
            return;
        }

        // ── 4. Succès — reset du compteur ──────────────────────────────────
        $this->userModel->resetLoginAttempts((int) $user['id']);
        $this->logActivity($user['id'], 'login_success', null, null, $ip, $email);

        $userPublic = $this->userModel->toArray($user);
        $token      = Auth::generateToken($userPublic);

        $this->json([
            'success' => true,
            'message' => 'Login successful.',
            'token'   => $token,
            'user'    => $userPublic,
        ]);
    }

    // ── Helper logging ──────────────────────────────────────────────────────────
    private function logActivity(?int $userId, string $action, ?string $entite, ?int $entiteId, string $ip, ?string $extra = null): void
    {
        try {
            $logModel = new \App\Models\ActivityLogModel();
            $logModel->log([
                'utilisateur_id' => $userId,
                'action'         => $action,
                'entite'         => $entite,
                'entite_id'      => $entiteId,
                'ip_address'     => $ip,
                'user_agent'     => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'donnees'        => $extra ? json_encode(['email' => $extra]) : null,
            ]);
        } catch (\Throwable $e) {
            // Log silencieux — ne pas casser l'auth si le log échoue
        }
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
