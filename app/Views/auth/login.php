<div class="auth-card">
    <h1 class="brand-hand mb-1">Cuponeras</h1>
    <p class="text-secondary mb-4">Entra para ver tus cupones.</p>

    <form method="post" action="<?= BASE_URL ?>/entrar" autocomplete="on">
        <?= Csrf::field() ?>

        <div class="mb-3">
            <label class="form-label" for="email">Correo</label>
            <input type="email" class="form-control" id="email" name="email"
                   autocomplete="username" required autofocus>
        </div>

        <div class="mb-4">
            <label class="form-label" for="password">Contraseña</label>
            <input type="password" class="form-control" id="password" name="password"
                   autocomplete="current-password" required>
        </div>

        <button type="submit" class="btn btn-accent w-100">Entrar</button>
    </form>
</div>
