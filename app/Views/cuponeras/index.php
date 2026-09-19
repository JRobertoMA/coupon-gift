<?php
/**
 * @var array $books
 * @var int   $me
 */
?>
<div class="d-flex flex-wrap align-items-center gap-3 mb-4">
    <h1 class="brand-hand mb-0 me-auto">Mis cuponeras</h1>
    <a class="btn btn-accent" href="<?= BASE_URL ?>/cuponeras/nueva">Nueva cuponera</a>
</div>

<?php if (!$books): ?>
    <?php /* Todo listado tiene su estado vacío. */ ?>
    <div class="empty-state">
        <p class="fs-5 mb-2">Todavía no hay ninguna cuponera.</p>
        <p class="text-secondary mb-3">
            Crea una, elige a quién va dirigida y se rellena con los 20 cupones del diseño
            para que los edites a tu gusto.
        </p>
        <a class="btn btn-accent" href="<?= BASE_URL ?>/cuponeras/nueva">Crear la primera</a>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($books as $book): ?>
            <?php
            $isOwner  = (int) $book['owner_id'] === $me;
            $total    = (int) $book['total'];
            $done     = (int) $book['fulfilled'];
            $percent  = $total > 0 ? round($done / $total * 100) : 0;
            ?>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card h-100 book-card">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-start gap-2 mb-2">
                            <h2 class="h5 mb-0 me-auto"><?= htmlspecialchars($book['title']) ?></h2>
                            <?php if ((int) $book['is_draft'] === 1): ?>
                                <span class="badge text-bg-secondary">Borrador</span>
                            <?php endif; ?>
                        </div>

                        <p class="text-secondary small mb-3">
                            <?php if ($isOwner): ?>
                                Para <strong><?= htmlspecialchars($book['recipient_name']) ?></strong>
                            <?php else: ?>
                                De <strong><?= htmlspecialchars($book['owner_name']) ?></strong>
                            <?php endif; ?>
                        </p>

                        <div class="mb-1 d-flex justify-content-between small text-secondary">
                            <span><?= $done ?> de <?= $total ?> cumplidos</span>
                            <span><?= $percent ?>%</span>
                        </div>
                        <div class="progress mb-3" style="height: 6px">
                            <div class="progress-bar bg-success" style="width: <?= $percent ?>%"></div>
                        </div>

                        <div class="mt-auto d-flex flex-wrap gap-2">
                            <a class="btn btn-sm btn-accent"
                               href="<?= BASE_URL ?>/c/<?= urlencode($book['slug']) ?>">Abrir</a>
                            <a class="btn btn-sm btn-outline-secondary"
                               href="<?= BASE_URL ?>/c/<?= urlencode($book['slug']) ?>/imprimir">Imprimir</a>
                            <?php if ($isOwner): ?>
                                <a class="btn btn-sm btn-outline-secondary"
                                   href="<?= BASE_URL ?>/cuponeras/<?= (int) $book['id'] ?>/editar">Editar</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
