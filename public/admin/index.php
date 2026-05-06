<?php
session_start();
define('ADMIN_GUARD', true);
require_once __DIR__ . '/config.php';

// ── Portable base-path detection ──────────────────────────────────────────────
// Works whether the project lives at the domain root (OVH) or in a subfolder
// (XAMPP: localhost/jetsetboat/). Derives paths purely from the current request
// so no manual configuration is needed.
$_scriptName = $_SERVER['SCRIPT_NAME'] ?? '/admin/index.php';
$_adminDir   = rtrim(dirname($_scriptName), '/\\');          // e.g. /admin  or /jetsetboat/admin
$_siteDir    = rtrim(dirname($_adminDir),   '/\\');          // e.g. ''      or /jetsetboat

define('ADMIN_BASE', $_adminDir . '/');                       // /admin/  or /jetsetboat/admin/
define('SITE_BASE',  ($_siteDir === '' || $_siteDir === '.') ? '/' : $_siteDir . '/');
// ──────────────────────────────────────────────────────────────────────────────

$eventsFile  = __DIR__ . '/../events.json';
$reviewsFile = __DIR__ . '/../reviews.json';
$uploadDir   = __DIR__ . '/../images/events/';
$uploadBase  = SITE_BASE . 'images/events/';   // /images/events/ or /jetsetboat/images/events/

/* ── Helpers ── */

function readEvents(): array {
    global $eventsFile;
    if (!file_exists($eventsFile)) return [];
    $data = json_decode(file_get_contents($eventsFile), true);
    return is_array($data) ? $data : [];
}

function writeEvents(array $events): void {
    global $eventsFile;
    file_put_contents(
        $eventsFile,
        json_encode(array_values($events), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
}

function readReviews(): array {
    global $reviewsFile;
    if (!file_exists($reviewsFile)) return [];
    $data = json_decode(file_get_contents($reviewsFile), true);
    return is_array($data) ? $data : [];
}

function writeReviews(array $reviews): void {
    global $reviewsFile;
    file_put_contents(
        $reviewsFile,
        json_encode(array_values($reviews), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
}

function isLoggedIn(): bool {
    return !empty($_SESSION['admin_logged_in']);
}

function requireLogin(): void {
    if (!isLoggedIn()) { header('Location: ' . ADMIN_BASE); exit; }
}

function redirect(string $url): void {
    header('Location: ' . $url); exit;
}

function setFlash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/* ── Actions ── */

if (isset($_GET['logout'])) {
    session_destroy();
    redirect(ADMIN_BASE);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* Login */
    if ($action === 'login') {
        $user = trim($_POST['username'] ?? '');
        $pass = $_POST['password'] ?? '';
        if (hash_equals(ADMIN_USER, $user) && hash_equals(ADMIN_PASS, $pass)) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            redirect(ADMIN_BASE);
        } else {
            setFlash('error', 'Identifiants incorrects. Vérifiez votre nom d\'utilisateur et mot de passe.');
            redirect(ADMIN_BASE);
        }
    }

    /* Save event */
    if ($action === 'save_event') {
        requireLogin();
        $id       = trim($_POST['id'] ?? '');
        $isNew    = ($id === '');
        $title    = trim($_POST['title'] ?? '');
        $date     = trim($_POST['date'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $desc     = trim($_POST['description'] ?? '');
        $price    = (float)($_POST['price'] ?? 0);
        $stripe   = trim($_POST['stripe_link'] ?? '');
        $featured  = !empty($_POST['featured']);
        $soldOut   = !empty($_POST['sold_out']);
        $spots     = max(0, (int)($_POST['spots']      ?? 0));
        $spotsLeft = max(0, (int)($_POST['spots_left'] ?? 0));
        if ($spotsLeft === 0 && $spots > 0) $soldOut = true;

        if (!$title || !$date || !$location || !$desc || !$price || !$stripe) {
            setFlash('error', 'Tous les champs obligatoires (*) doivent être remplis.');
            redirect(ADMIN_BASE . '?page=' . ($isNew ? 'new' : 'edit&id=' . urlencode($id)));
        }

        $events = readEvents();
        $existingImage = '';
        if (!$isNew) {
            foreach ($events as $ev) {
                if ($ev['id'] === $id) { $existingImage = $ev['image'] ?? ''; break; }
            }
        }

        $imagePath = $existingImage;

        /* Handle file upload */
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            global $uploadDir, $uploadBase;
            $file  = $_FILES['image'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
                'image/gif'  => 'gif',
            ];

            if (!isset($allowedMimes[$mime])) {
                setFlash('error', 'Format d\'image non autorisé. Utilisez JPG, PNG ou WebP.');
                redirect(ADMIN_BASE . '?page=' . ($isNew ? 'new' : 'edit&id=' . urlencode($id)));
            }
            if ($file['size'] > 5 * 1024 * 1024) {
                setFlash('error', 'L\'image dépasse 5 MB.');
                redirect(ADMIN_BASE . '?page=' . ($isNew ? 'new' : 'edit&id=' . urlencode($id)));
            }

            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $ext      = $allowedMimes[$mime];
            $filename = uniqid('event_', true) . '.' . $ext;

            if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                setFlash('error', 'Impossible d\'enregistrer l\'image. Vérifiez les permissions du dossier images/events/.');
                redirect(ADMIN_BASE . '?page=' . ($isNew ? 'new' : 'edit&id=' . urlencode($id)));
            }
            $imagePath = $uploadBase . $filename;

        } elseif (!empty($_POST['image_url'])) {
            $url = filter_var(trim($_POST['image_url']), FILTER_VALIDATE_URL);
            if ($url) $imagePath = $url;
        }

        $newEvent = [
            'id'          => $isNew ? uniqid('evt', true) : $id,
            'title'       => $title,
            'date'        => $date,
            'location'    => $location,
            'description' => $desc,
            'image'       => $imagePath,
            'price'       => $price,
            'stripeLink'  => $stripe,
            'featured'    => $featured,
            'soldOut'     => $soldOut,
            'spots'       => $spots,
            'spotsLeft'   => $spotsLeft,
        ];

        if ($isNew) {
            $events[] = $newEvent;
        } else {
            foreach ($events as &$ev) {
                if ($ev['id'] === $id) { $ev = $newEvent; break; }
            }
            unset($ev);
        }

        writeEvents($events);
        setFlash('success', $isNew ? 'Événement créé avec succès !' : 'Événement mis à jour !');
        redirect(ADMIN_BASE);
    }

    /* Delete event */
    if ($action === 'delete_event') {
        requireLogin();
        $id = trim($_POST['id'] ?? '');
        if ($id) {
            $events = readEvents();
            $events = array_values(array_filter($events, fn($ev) => $ev['id'] !== $id));
            writeEvents($events);
            setFlash('success', 'Événement supprimé.');
        }
        redirect(ADMIN_BASE);
    }

    /* Approve review */
    if ($action === 'approve_review') {
        requireLogin();
        $id = trim($_POST['id'] ?? '');
        if ($id) {
            $reviews = readReviews();
            foreach ($reviews as &$rev) {
                if ($rev['id'] === $id) { $rev['status'] = 'approved'; break; }
            }
            unset($rev);
            writeReviews($reviews);
            setFlash('success', 'Avis approuvé et publié sur le site.');
        }
        redirect(ADMIN_BASE . '?page=reviews');
    }

    /* Delete review */
    if ($action === 'delete_review') {
        requireLogin();
        $id = trim($_POST['id'] ?? '');
        if ($id) {
            $reviews = readReviews();
            $reviews = array_values(array_filter($reviews, fn($r) => $r['id'] !== $id));
            writeReviews($reviews);
            setFlash('success', 'Avis supprimé.');
        }
        redirect(ADMIN_BASE . '?page=reviews');
    }

    /* Save review (edit) */
    if ($action === 'save_review') {
        requireLogin();
        $id      = trim($_POST['id']        ?? '');
        $first   = trim($_POST['firstName'] ?? '');
        $last    = trim($_POST['lastName']  ?? '');
        $rating  = max(1, min(5, (int)($_POST['rating']  ?? 5)));
        $comment = trim($_POST['comment']   ?? '');
        $city    = trim($_POST['city']      ?? '');

        if (!$first || !$last || !$comment) {
            setFlash('error', 'Tous les champs obligatoires doivent être remplis.');
            redirect(ADMIN_BASE . '?page=edit_review&id=' . urlencode($id));
        }

        $reviews = readReviews();
        foreach ($reviews as &$rev) {
            if ($rev['id'] === $id) {
                $rev['firstName'] = $first;
                $rev['lastName']  = $last;
                $rev['rating']    = $rating;
                $rev['comment']   = $comment;
                if ($city !== '') $rev['city'] = $city; else unset($rev['city']);
                break;
            }
        }
        unset($rev);
        writeReviews($reviews);
        setFlash('success', 'Avis modifié avec succès.');
        redirect(ADMIN_BASE . '?page=reviews');
    }

    /* Change password */
    if ($action === 'change_password') {
        requireLogin();
        $currentPass = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password']      ?? '';
        $confirmPass = $_POST['confirm_password']  ?? '';

        if (!hash_equals(ADMIN_PASS, $currentPass)) {
            setFlash('error', 'Mot de passe actuel incorrect.');
            redirect(ADMIN_BASE . '?page=password');
        }
        if (strlen($newPass) < 8) {
            setFlash('error', 'Le nouveau mot de passe doit contenir au moins 8 caractères.');
            redirect(ADMIN_BASE . '?page=password');
        }
        if ($newPass !== $confirmPass) {
            setFlash('error', 'Le nouveau mot de passe et sa confirmation ne correspondent pas.');
            redirect(ADMIN_BASE . '?page=password');
        }

        $configContent = "<?php\n"
            . "if (!defined('ADMIN_GUARD')) { http_response_code(403); exit; }\n\n"
            . "// ── Admin credentials ────────────────────────────────────────────────────────\n"
            . "// Change ADMIN_PASS before sharing access.\n"
            . "define('ADMIN_USER', '" . addslashes(ADMIN_USER) . "');\n"
            . "define('ADMIN_PASS', '" . addslashes($newPass) . "');\n";

        if (file_put_contents(__DIR__ . '/config.php', $configContent) === false) {
            setFlash('error', 'Impossible de mettre à jour config.php. Vérifiez les permissions du fichier.');
            redirect(ADMIN_BASE . '?page=password');
        }

        setFlash('success', 'Mot de passe modifié avec succès.');
        redirect(ADMIN_BASE);
    }
}

/* ── Render ── */

$flash  = getFlash();
$page   = $_GET['page'] ?? 'list';
$editId = $_GET['id'] ?? '';

if (!isLoggedIn()) {
    renderLogin($flash);
    exit;
}

$events    = readEvents();
$editEvent = null;

if ($page === 'edit' && $editId) {
    foreach ($events as $ev) {
        if ($ev['id'] === $editId) { $editEvent = $ev; break; }
    }
    if (!$editEvent) {
        setFlash('error', 'Événement introuvable.');
        redirect(ADMIN_BASE);
    }
}

usort($events, fn($a, $b) => strcmp($a['date'], $b['date']));

$editReview = null;
if ($page === 'edit_review' && $editId) {
    foreach (readReviews() as $r) {
        if ($r['id'] === $editId) { $editReview = $r; break; }
    }
    if (!$editReview) {
        setFlash('error', 'Avis introuvable.');
        redirect(ADMIN_BASE . '?page=reviews');
    }
}

renderAdmin($page, $events, $editEvent, $flash, $editReview);

/* ══════════════════════════════════════════════════════════════════════════
   RENDER FUNCTIONS
   ══════════════════════════════════════════════════════════════════════════ */

function css(): string {
    return '
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f0f2f5;color:#1a1a2e;min-height:100vh}
a{color:inherit;text-decoration:none}

/* Header */
.ah{background:#1A2332;color:#fff;padding:0 1.5rem;height:60px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 2px 8px rgba(0,0,0,.3)}
.ah-logo{font-size:1.1rem;font-weight:700;letter-spacing:.03em}
.ah-logo span{color:#FF6B35}
.ah-nav{display:flex;gap:.625rem;align-items:center;flex-wrap:wrap}

/* Buttons */
.btn{display:inline-block;padding:.45rem 1rem;border-radius:6px;font-size:.875rem;font-weight:500;border:none;cursor:pointer;transition:background .2s,opacity .2s;text-align:center}
.btn-orange{background:#FF6B35;color:#fff}.btn-orange:hover{background:#e55a25}
.btn-ghost{background:rgba(255,255,255,.1);color:#fff}.btn-ghost:hover{background:rgba(255,255,255,.2)}
.btn-dark{background:#1A2332;color:#fff}.btn-dark:hover{background:#263549}
.btn-red{background:#fee2e2;color:#dc2626}.btn-red:hover{background:#fca5a5}
.btn-gray{background:#f3f4f6;color:#374151}.btn-gray:hover{background:#e5e7eb}
.btn-sm{padding:.35rem .75rem;font-size:.8rem}

/* Main */
.main{max-width:1100px;margin:2rem auto;padding:0 1.5rem}

/* Alert */
.alert{padding:.875rem 1.25rem;border-radius:8px;margin-bottom:1.5rem;font-size:.9rem}
.alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5}
.alert-success{background:#dcfce7;color:#166534;border:1px solid #86efac}

/* Page header */
.ph{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;gap:1rem;flex-wrap:wrap}
.ph h1{font-size:1.4rem;font-weight:700;color:#1A2332}

/* Events list */
.ev-list{display:grid;gap:.875rem}
.ev-row{background:#fff;border-radius:10px;padding:1.125rem 1.25rem;display:flex;align-items:center;gap:1.125rem;box-shadow:0 1px 4px rgba(0,0,0,.06);border:1px solid #e5e7eb}
.ev-img{width:72px;height:56px;object-fit:cover;border-radius:6px;flex-shrink:0;background:#e5e7eb}
.ev-info{flex:1;min-width:0}
.ev-title{font-weight:600;color:#1A2332;margin-bottom:.25rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ev-meta{font-size:.78rem;color:#6b7280;display:flex;gap:.875rem;flex-wrap:wrap;align-items:center}
.badge-star{display:inline-flex;align-items:center;gap:.25rem;padding:.1rem .5rem;background:#fff7ed;color:#ea580c;border:1px solid #fed7aa;border-radius:50px;font-size:.68rem;font-weight:700}
.ev-actions{display:flex;gap:.5rem;flex-shrink:0}

/* Form card */
.fc{background:#fff;border-radius:12px;padding:2rem;box-shadow:0 1px 4px rgba(0,0,0,.06);border:1px solid #e5e7eb}
.fg{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem}
.fg-group{display:flex;flex-direction:column;gap:.375rem}
.fg-group.full{grid-column:1/-1}
label{font-size:.8rem;font-weight:600;color:#374151;letter-spacing:.03em}
input[type=text],input[type=date],input[type=number],input[type=url],textarea{font-family:inherit;font-size:.9rem;padding:.65rem .9rem;border:1.5px solid #d1d5db;border-radius:8px;width:100%;transition:border-color .2s,box-shadow .2s;color:#111827;background:#fff}
input:focus,textarea:focus{outline:none;border-color:#FF6B35;box-shadow:0 0 0 3px rgba(255,107,53,.15)}
textarea{resize:vertical;min-height:100px}
input[type=file]{padding:.5rem;border:1.5px dashed #d1d5db;border-radius:8px;cursor:pointer;background:#f9fafb;width:100%;font-size:.875rem}
input[type=file]:hover{border-color:#FF6B35}
.chk-row{display:flex;align-items:center;gap:.5rem;flex-direction:row}
.chk-row input{width:auto;accent-color:#FF6B35}
.chk-row label{font-size:.9rem;font-weight:500;letter-spacing:0}
.fa{display:flex;gap:.875rem;margin-top:1.5rem;flex-wrap:wrap}
.hint{font-size:.75rem;color:#9ca3af;margin-top:.25rem}
.cur-img img{width:120px;height:80px;object-fit:cover;border-radius:6px;border:1px solid #e5e7eb;margin-bottom:.375rem}

/* Login */
.lw{min-height:100vh;display:flex;align-items:center;justify-content:center;background:#1A2332}
.lc{background:#fff;border-radius:16px;padding:2.5rem;width:100%;max-width:400px;box-shadow:0 8px 40px rgba(0,0,0,.3)}
.ll{font-size:1.4rem;font-weight:700;text-align:center;margin-bottom:1.75rem;color:#1A2332}
.ll span{color:#FF6B35}
.lc label{font-size:.85rem}
.lc .fg-group{margin-bottom:1rem}
.btn-login{background:#FF6B35;color:#fff;width:100%;padding:.875rem;border-radius:8px;font-size:1rem;font-weight:600;border:none;cursor:pointer;margin-top:.5rem}
.btn-login:hover{background:#e55a25}

/* Empty state */
.empty{text-align:center;padding:4rem 2rem;color:#6b7280}
.empty-ico{font-size:3rem;margin-bottom:1rem}

/* Reviews */
.rv-section{margin-bottom:.5rem}
.rv-section-title{font-size:1rem;font-weight:700;margin-bottom:.875rem;padding-left:.75rem}
.pending-title{color:#ea580c;border-left:3px solid #ea580c}
.approved-title{color:#16a34a;border-left:3px solid #16a34a}
.rv-empty{color:#9ca3af;font-size:.875rem;padding:.5rem 0}
.rv-avatar{width:40px;height:40px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;color:#fff;flex-shrink:0;letter-spacing:.02em}
.rv-comment{font-size:.8rem;color:#6b7280;margin-top:.25rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:480px}
.rv-stars{color:#ea580c;font-size:.9rem;letter-spacing:.05em}
.btn-green{background:#dcfce7;color:#15803d}.btn-green:hover{background:#bbf7d0}

/* Delete modal */
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center}
.modal.on{display:flex}
.mc{background:#fff;border-radius:12px;padding:2rem;max-width:420px;width:90%;box-shadow:0 8px 40px rgba(0,0,0,.3)}
.mc h3{font-size:1.2rem;font-weight:700;color:#1A2332;margin-bottom:.625rem}
.mc p{color:#6b7280;font-size:.9rem;margin-bottom:1.5rem}
.mc-actions{display:flex;gap:.75rem}

@media(max-width:640px){
  .fg{grid-template-columns:1fr}
  .ev-row{flex-wrap:wrap}
  .ah{padding:0 1rem;height:auto;padding:.75rem 1rem;flex-wrap:wrap;gap:.5rem}
  .main{padding:0 1rem}
  .fc{padding:1.25rem}
}
';
}

function renderLogin(?array $flash): void { ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Connexion — JetSetBoat Admin</title>
<style><?= css() ?></style>
</head>
<body>
<div class="lw">
  <div class="lc">
    <div class="ll">JetSet<span>Boat</span> Admin</div>

    <?php if ($flash): ?>
      <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <input type="hidden" name="action" value="login">
      <div class="fg-group">
        <label for="username">Nom d'utilisateur</label>
        <input type="text" id="username" name="username" required autocomplete="username" placeholder="mayeul">
      </div>
      <div class="fg-group">
        <label for="password">Mot de passe</label>
        <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••••">
      </div>
      <button type="submit" class="btn-login">Se connecter</button>
    </form>
  </div>
</div>
</body>
</html>
<?php }

function renderAdmin(string $page, array $events, ?array $editEvent, ?array $flash, ?array $editReview = null): void { ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin — JetSetBoat</title>
<style><?= css() ?></style>
</head>
<body>

<header class="ah">
  <div class="ah-logo">JetSet<span>Boat</span> <span style="font-weight:400;opacity:.6;font-size:.9rem">Admin</span></div>
  <nav class="ah-nav">
    <a href="?" class="btn btn-ghost">Événements</a>
    <a href="?page=reviews" class="btn btn-ghost">Avis</a>
    <a href="?page=new" class="btn btn-orange">+ Nouvel événement</a>
    <a href="<?= h(SITE_BASE) ?>" class="btn btn-ghost" target="_blank">Voir le site ↗</a>
    <a href="?page=password" class="btn btn-ghost">Mot de passe</a>
    <a href="?logout=1" class="btn btn-ghost">Déconnexion</a>
  </nav>
</header>

<main class="main">

  <?php if ($flash): ?>
    <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
  <?php endif; ?>

  <?php if ($page === 'list'): ?>
    <!-- ─── Events list ─── -->
    <div class="ph">
      <h1>Événements (<?= count($events) ?>)</h1>
      <a href="?page=new" class="btn btn-orange">+ Nouvel événement</a>
    </div>

    <?php if (empty($events)): ?>
      <div class="empty">
        <div class="empty-ico">🌊</div>
        <p style="margin-bottom:1rem">Aucun événement pour le moment.</p>
        <a href="?page=new" style="color:#FF6B35;font-weight:600">Créer le premier événement →</a>
      </div>

    <?php else: ?>
      <div class="ev-list">
        <?php foreach ($events as $ev): ?>
          <div class="ev-row">
            <?php if (!empty($ev['image'])): ?>
              <img class="ev-img" src="<?= h($ev['image']) ?>" alt="" loading="lazy">
            <?php else: ?>
              <div class="ev-img" style="display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:1.5rem">🏖️</div>
            <?php endif; ?>

            <div class="ev-info">
              <div class="ev-title">
                <?= h($ev['title']) ?>
                <?php if (!empty($ev['featured'])): ?>
                  <span class="badge-star">★ Vedette</span>
                <?php endif; ?>
              </div>
              <div class="ev-meta">
                <span>📅 <?= h($ev['date']) ?></span>
                <span>📍 <?= h($ev['location']) ?></span>
                <span>💶 <?= h((string)$ev['price']) ?> €</span>
                <a href="<?= h($ev['stripeLink']) ?>" target="_blank" rel="noopener" style="color:#FF6B35">Stripe ↗</a>
                <?php if (!empty($ev['soldOut'])): ?>
                  <span style="background:#fee2e2;color:#dc2626;padding:.1rem .5rem;border-radius:50px;font-size:.68rem;font-weight:700">Complet</span>
                <?php elseif (isset($ev['spotsLeft']) && $ev['spotsLeft'] <= 5 && $ev['spotsLeft'] > 0): ?>
                  <span style="background:#fff7ed;color:#ea580c;padding:.1rem .5rem;border-radius:50px;font-size:.68rem;font-weight:700">⚡ <?= h((string)$ev['spotsLeft']) ?> pl. restantes</span>
                <?php elseif (isset($ev['spots']) && $ev['spots'] > 0): ?>
                  <span><?= h((string)($ev['spotsLeft'] ?? $ev['spots'])) ?>/<?= h((string)$ev['spots']) ?> places</span>
                <?php endif; ?>
              </div>
            </div>

            <div class="ev-actions">
              <a href="?page=edit&id=<?= urlencode($ev['id']) ?>" class="btn btn-dark btn-sm">Modifier</a>
              <button type="button" class="btn btn-red btn-sm"
                      data-id="<?= h($ev['id']) ?>"
                      data-title="<?= h($ev['title']) ?>"
                      onclick="openDel(this)">Supprimer</button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php elseif ($page === 'new' || $page === 'edit'): ?>
    <!-- ─── Event form ─── -->
    <div class="ph">
      <h1><?= $page === 'new' ? 'Nouvel événement' : 'Modifier l\'événement' ?></h1>
      <a href="?" class="btn btn-gray">← Retour</a>
    </div>

    <div class="fc">
      <form method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save_event">
        <?php if ($editEvent): ?>
          <input type="hidden" name="id" value="<?= h($editEvent['id']) ?>">
        <?php endif; ?>

        <div class="fg">

          <div class="fg-group">
            <label for="title">Titre *</label>
            <input type="text" id="title" name="title" required
                   value="<?= h($editEvent['title'] ?? '') ?>"
                   placeholder="JetSet Sunset Saint-Tropez">
          </div>

          <div class="fg-group">
            <label for="date">Date *</label>
            <input type="date" id="date" name="date" required
                   value="<?= h($editEvent['date'] ?? '') ?>">
          </div>

          <div class="fg-group">
            <label for="location">Lieu *</label>
            <input type="text" id="location" name="location" required
                   value="<?= h($editEvent['location'] ?? '') ?>"
                   placeholder="Saint-Tropez">
          </div>

          <div class="fg-group">
            <label for="price">Prix (€) *</label>
            <input type="number" id="price" name="price" required min="1" step="0.01"
                   value="<?= h((string)($editEvent['price'] ?? '')) ?>"
                   placeholder="45">
          </div>

          <div class="fg-group full">
            <label for="description">Description *</label>
            <textarea id="description" name="description" required
                      placeholder="Décrivez l'ambiance, le programme de la soirée..."><?= h($editEvent['description'] ?? '') ?></textarea>
          </div>

          <div class="fg-group full">
            <label for="stripe_link">Lien de paiement Stripe *</label>
            <input type="url" id="stripe_link" name="stripe_link" required
                   value="<?= h($editEvent['stripeLink'] ?? '') ?>"
                   placeholder="https://buy.stripe.com/...">
            <p class="hint">Créez le lien sur dashboard.stripe.com → Payment Links, puis collez-le ici.</p>
          </div>

          <div class="fg-group full">
            <label>Image de l'événement</label>
            <?php if (!empty($editEvent['image'])): ?>
              <div class="cur-img" style="margin-bottom:.75rem">
                <img src="<?= h($editEvent['image']) ?>" alt="Image actuelle">
                <p class="hint">Image actuelle. Laissez le champ vide pour la conserver.</p>
              </div>
            <?php endif; ?>
            <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
            <p class="hint">JPG, PNG ou WebP — max 5 MB. Ou entrez une URL ci-dessous.</p>
          </div>

          <div class="fg-group full">
            <label for="image_url">URL de l'image (si pas de fichier)</label>
            <input type="url" id="image_url" name="image_url"
                   value="<?= (!empty($editEvent['image']) && strpos($editEvent['image'], 'http') === 0) ? h($editEvent['image']) : '' ?>"
                   placeholder="https://exemple.com/mon-image.jpg">
          </div>

          <div class="fg-group full">
            <div class="chk-row">
              <input type="checkbox" id="featured" name="featured" value="1"
                     <?= !empty($editEvent['featured']) ? 'checked' : '' ?>>
              <label for="featured">Mettre cet événement en avant (carte vedette)</label>
            </div>
          </div>

          <div class="fg-group full">
            <div class="chk-row">
              <input type="checkbox" id="sold_out" name="sold_out" value="1"
                     <?= !empty($editEvent['soldOut']) ? 'checked' : '' ?>>
              <label for="sold_out">Sold out — marquer l'événement comme complet</label>
            </div>
            <p class="hint">Se coche automatiquement quand les places restantes atteignent 0. Désactive le bouton de réservation sur le site.</p>
          </div>

          <div class="fg-group">
            <label for="spots">Nombre total de places</label>
            <input type="number" id="spots" name="spots" min="0" step="1"
                   value="<?= h((string)($editEvent['spots'] ?? '')) ?>"
                   placeholder="80">
            <p class="hint">Capacité totale de l'événement (0 = non limité).</p>
          </div>

          <div class="fg-group">
            <label for="spots_left">Places restantes</label>
            <input type="number" id="spots_left" name="spots_left" min="0" step="1"
                   value="<?= h((string)($editEvent['spotsLeft'] ?? '')) ?>"
                   placeholder="80">
            <p class="hint">Mise à jour manuelle après chaque vente. Passe en "Complet" automatiquement à 0.</p>
          </div>

        </div>

        <div class="fa">
          <button type="submit" class="btn btn-orange" style="padding:.7rem 1.75rem;font-size:.95rem">
            <?= $page === 'new' ? 'Créer l\'événement' : 'Enregistrer les modifications' ?>
          </button>
          <a href="?" class="btn btn-gray" style="padding:.7rem 1.25rem;font-size:.95rem">Annuler</a>
        </div>

      </form>
    </div>

  <?php elseif ($page === 'password'): ?>
    <!-- ─── Change password ─── -->
    <div class="ph">
      <h1>Changer le mot de passe</h1>
      <a href="?" class="btn btn-gray">← Retour</a>
    </div>

    <div class="fc" style="max-width:480px">
      <form method="POST" action="">
        <input type="hidden" name="action" value="change_password">
        <div class="fg" style="grid-template-columns:1fr">

          <div class="fg-group">
            <label for="current_password">Mot de passe actuel</label>
            <input type="password" id="current_password" name="current_password"
                   required autocomplete="current-password" placeholder="••••••••">
          </div>

          <div class="fg-group">
            <label for="new_password">Nouveau mot de passe</label>
            <input type="password" id="new_password" name="new_password"
                   required autocomplete="new-password" minlength="8" placeholder="••••••••">
            <p class="hint">Minimum 8 caractères.</p>
          </div>

          <div class="fg-group">
            <label for="confirm_password">Confirmer le nouveau mot de passe</label>
            <input type="password" id="confirm_password" name="confirm_password"
                   required autocomplete="new-password" minlength="8" placeholder="••••••••">
          </div>

        </div>
        <div class="fa">
          <button type="submit" class="btn btn-orange" style="padding:.7rem 1.75rem;font-size:.95rem">
            Enregistrer le nouveau mot de passe
          </button>
          <a href="?" class="btn btn-gray" style="padding:.7rem 1.25rem;font-size:.95rem">Annuler</a>
        </div>
      </form>
    </div>

  <?php elseif ($page === 'reviews'): ?>
    <!-- ─── Reviews list ─── -->
    <?php
      $allReviews = readReviews();
      $pending  = array_values(array_filter($allReviews, fn($r) => ($r['status'] ?? '') === 'pending'));
      $approved = array_values(array_filter($allReviews, fn($r) => ($r['status'] ?? '') === 'approved'));
      $avatarColors = ['#FF6B35','#1A3A5C','#F4A261','#2E7D6B','#7B3FAB'];
      function reviewAvatar(array $r, int $idx, array $colors): string {
          $col = $colors[$idx % count($colors)];
          $ini = strtoupper(($r['firstName'][0] ?? '?') . ($r['lastName'][0] ?? '?'));
          return "<span class='rv-avatar' style='background:{$col}'>{$ini}</span>";
      }
      function reviewStars(int $n): string {
          return '<span class="rv-stars">' . str_repeat('★',$n) . '<span style="opacity:.3">' . str_repeat('★',5-$n) . '</span></span>';
      }
    ?>
    <div class="ph">
      <h1>Avis clients (<?= count($allReviews) ?>)</h1>
    </div>

    <!-- En attente -->
    <div class="rv-section">
      <h2 class="rv-section-title pending-title">En attente de validation (<?= count($pending) ?>)</h2>
      <?php if (empty($pending)): ?>
        <p class="rv-empty">Aucun avis en attente.</p>
      <?php else: ?>
        <div class="ev-list">
          <?php foreach ($pending as $i => $r): ?>
            <div class="ev-row">
              <?= reviewAvatar($r, $i, $avatarColors) ?>
              <div class="ev-info">
                <div class="ev-title"><?= h($r['firstName']) ?> <?= h($r['lastName']) ?></div>
                <div class="ev-meta">
                  <?= reviewStars((int)($r['rating'] ?? 5)) ?>
                  <span style="color:#9ca3af"><?= h($r['date'] ?? '') ?></span>
                </div>
                <div class="rv-comment"><?= h($r['comment']) ?></div>
              </div>
              <div class="ev-actions">
                <form method="POST" action="" style="display:inline">
                  <input type="hidden" name="action" value="approve_review">
                  <input type="hidden" name="id" value="<?= h($r['id']) ?>">
                  <button type="submit" class="btn btn-green btn-sm">✓ Approuver</button>
                </form>
                <button type="button" class="btn btn-red btn-sm"
                        data-id="<?= h($r['id']) ?>"
                        data-name="<?= h($r['firstName'] . ' ' . $r['lastName']) ?>"
                        onclick="openDelRev(this)">Supprimer</button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Publiés -->
    <div class="rv-section" style="margin-top:2rem">
      <h2 class="rv-section-title approved-title">Avis publiés (<?= count($approved) ?>)</h2>
      <?php if (empty($approved)): ?>
        <p class="rv-empty">Aucun avis publié.</p>
      <?php else: ?>
        <div class="ev-list">
          <?php foreach ($approved as $i => $r): ?>
            <div class="ev-row">
              <?= reviewAvatar($r, $i, $avatarColors) ?>
              <div class="ev-info">
                <div class="ev-title">
                  <?= h($r['firstName']) ?> <?= h($r['lastName']) ?>
                  <?php if (!empty($r['city'])): ?>
                    <span style="font-weight:400;opacity:.6;font-size:.8rem">— <?= h($r['city']) ?></span>
                  <?php endif; ?>
                </div>
                <div class="ev-meta">
                  <?= reviewStars((int)($r['rating'] ?? 5)) ?>
                  <span style="color:#9ca3af"><?= h($r['date'] ?? '') ?></span>
                </div>
                <div class="rv-comment"><?= h($r['comment']) ?></div>
              </div>
              <div class="ev-actions">
                <a href="?page=edit_review&id=<?= urlencode($r['id']) ?>" class="btn btn-dark btn-sm">Modifier</a>
                <button type="button" class="btn btn-red btn-sm"
                        data-id="<?= h($r['id']) ?>"
                        data-name="<?= h($r['firstName'] . ' ' . $r['lastName']) ?>"
                        onclick="openDelRev(this)">Supprimer</button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  <?php elseif ($page === 'edit_review' && $editReview): ?>
    <!-- ─── Edit review ─── -->
    <div class="ph">
      <h1>Modifier l'avis</h1>
      <a href="?page=reviews" class="btn btn-gray">← Retour</a>
    </div>

    <div class="fc" style="max-width:600px">
      <form method="POST" action="">
        <input type="hidden" name="action" value="save_review">
        <input type="hidden" name="id" value="<?= h($editReview['id']) ?>">
        <div class="fg">

          <div class="fg-group">
            <label for="rv-first">Prénom *</label>
            <input type="text" id="rv-first" name="firstName" required
                   value="<?= h($editReview['firstName'] ?? '') ?>">
          </div>

          <div class="fg-group">
            <label for="rv-last">Nom *</label>
            <input type="text" id="rv-last" name="lastName" required
                   value="<?= h($editReview['lastName'] ?? '') ?>">
          </div>

          <div class="fg-group">
            <label for="rv-city">Ville (optionnel)</label>
            <input type="text" id="rv-city" name="city"
                   value="<?= h($editReview['city'] ?? '') ?>" placeholder="Cannes">
          </div>

          <div class="fg-group">
            <label for="rv-rating">Note (1–5)</label>
            <input type="number" id="rv-rating" name="rating" min="1" max="5" required
                   value="<?= h((string)($editReview['rating'] ?? 5)) ?>">
          </div>

          <div class="fg-group full">
            <label for="rv-comment">Commentaire *</label>
            <textarea id="rv-comment" name="comment" required><?= h($editReview['comment'] ?? '') ?></textarea>
          </div>

        </div>
        <div class="fa">
          <button type="submit" class="btn btn-orange" style="padding:.7rem 1.75rem;font-size:.95rem">
            Enregistrer les modifications
          </button>
          <a href="?page=reviews" class="btn btn-gray" style="padding:.7rem 1.25rem;font-size:.95rem">Annuler</a>
        </div>
      </form>
    </div>

  <?php endif; ?>

</main>

<!-- Delete review modal -->
<div class="modal" id="delRevModal">
  <div class="mc">
    <h3>Supprimer l'avis ?</h3>
    <p id="delRevText">Cette action est irréversible.</p>
    <form method="POST" action="">
      <input type="hidden" name="action" value="delete_review">
      <input type="hidden" name="id" id="delRevId">
      <div class="mc-actions">
        <button type="submit" class="btn btn-orange" style="background:#dc2626">Supprimer définitivement</button>
        <button type="button" class="btn btn-gray" onclick="closeDelRev()">Annuler</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete confirmation modal -->
<div class="modal" id="delModal">
  <div class="mc">
    <h3>Supprimer l'événement ?</h3>
    <p id="delText">Cette action est irréversible.</p>
    <form method="POST" action="">
      <input type="hidden" name="action" value="delete_event">
      <input type="hidden" name="id" id="delId">
      <div class="mc-actions">
        <button type="submit" class="btn btn-orange" style="background:#dc2626">Supprimer définitivement</button>
        <button type="button" class="btn btn-gray" onclick="closeDel()">Annuler</button>
      </div>
    </form>
  </div>
</div>

<script>
function openDel(btn) {
  document.getElementById('delId').value    = btn.dataset.id;
  document.getElementById('delText').textContent =
    'L\'événement "' + btn.dataset.title + '" sera supprimé définitivement de events.json.';
  document.getElementById('delModal').classList.add('on');
}
function closeDel() {
  document.getElementById('delModal').classList.remove('on');
}
document.getElementById('delModal').addEventListener('click', function(e) {
  if (e.target === this) closeDel();
});

function openDelRev(btn) {
  document.getElementById('delRevId').value = btn.dataset.id;
  document.getElementById('delRevText').textContent =
    'L\'avis de "' + btn.dataset.name + '" sera supprimé définitivement.';
  document.getElementById('delRevModal').classList.add('on');
}
function closeDelRev() {
  document.getElementById('delRevModal').classList.remove('on');
}
document.getElementById('delRevModal').addEventListener('click', function(e) {
  if (e.target === this) closeDelRev();
});
</script>

</body>
</html>
<?php }
