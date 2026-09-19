<?php

declare(strict_types=1);

/**
 * Acceso.
 *
 * No hay registro abierto a propósito: la app es para dos personas. Las cuentas
 * se crean desde la línea de órdenes con cli/user.php.
 */
class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            $this->redirect('/cuponeras');
        }
        $this->render('auth/login', ['title' => 'Entrar'], 'auth');
    }

    public function login(): void
    {
        Csrf::require();

        $email    = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $this->flash('Escribe tu correo y tu contraseña.', 'danger');
            $this->redirect('/entrar');
        }

        // Se comprueba el bloqueo ANTES de tocar la contraseña, para que un
        // atacante no pueda seguir probando aunque acierte.
        if (Auth::isLocked($email)) {
            $this->flash(
                'Demasiados intentos fallidos. Espera ' . Auth::lockMinutes() . ' minutos.',
                'danger'
            );
            $this->redirect('/entrar');
        }

        $user = (new User())->findByEmail($email);

        if ($user === null || !password_verify($password, $user['password_hash'])) {
            // Solo se registran los intentos fallidos.
            Auth::recordFailure($email);
            // Mismo mensaje en los dos casos: no se revela si el correo existe.
            $this->flash('Correo o contraseña incorrectos.', 'danger');
            $this->redirect('/entrar');
        }

        Auth::clearAttempts($email);
        Auth::login($user);

        $intended = $_SESSION['intended'] ?? '/cuponeras';
        unset($_SESSION['intended']);
        $this->redirect($intended);
    }

    public function logout(): void
    {
        Auth::logout();
        $this->redirect('/entrar');
    }
}
