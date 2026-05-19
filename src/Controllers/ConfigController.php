<?php
namespace DojoManager\Controllers;

use DojoManager\Database;
use DojoManager\Services\CsrfService;

class ConfigController
{
    public function index(): void
    {
        (new AuthController())->requireAuth('admin');
        $id  = (int)$_SESSION['academia_id'];
        $pdo = Database::getConnection();

        $academia  = $pdo->prepare("SELECT * FROM academias WHERE id = ?");
        $academia->execute([$id]);
        $academia  = $academia->fetch();

        $planos    = $pdo->query("SELECT * FROM planos WHERE ativo=1 ORDER BY preco_mensal")->fetchAll();
        $csrfToken = CsrfService::generate();

        require ROOT . '/templates/configuracoes/index.php';
    }

    public function uploadLogo(): void
    {
        (new AuthController())->requireAuth('admin');
        CsrfService::validate($_POST['_csrf'] ?? '');

        $id   = (int)$_SESSION['academia_id'];
        $file = $_FILES['logo'] ?? null;

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['flash_erros'] = ['Erro no upload do arquivo.'];
            header('Location: /configuracoes');
            exit;
        }

        if ($file['size'] > UPLOAD_MAX_SIZE) {
            $_SESSION['flash_erros'] = ['Arquivo muito grande. Máximo 2MB.'];
            header('Location: /configuracoes');
            exit;
        }

        // Valida tipo real do arquivo (não apenas extensão)
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeReal = $finfo->file($file['tmp_name']);

        if (!in_array($mimeReal, UPLOAD_ALLOWED)) {
            $_SESSION['flash_erros'] = ['Formato inválido. Use JPG, PNG ou WebP.'];
            header('Location: /configuracoes');
            exit;
        }

        $ext      = match($mimeReal) {
            'image/jpeg' => 'jpg', 'image/png' => 'png',
            'image/webp' => 'webp', 'image/gif' => 'gif',
            default      => 'jpg'
        };

        $uploadDir = UPLOAD_PATH . "academias/{$id}/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        // Remove logo antiga
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare("SELECT logo_path FROM academias WHERE id = ?");
        $stmt->execute([$id]);
        $old  = $stmt->fetchColumn();
        if ($old && file_exists(ROOT . '/public' . $old)) {
            @unlink(ROOT . '/public' . $old);
        }

        $nomeArquivo = "logo_{$id}_" . bin2hex(random_bytes(8)) . ".{$ext}";
        $destino     = $uploadDir . $nomeArquivo;

        if (!move_uploaded_file($file['tmp_name'], $destino)) {
            $_SESSION['flash_erros'] = ['Falha ao salvar o arquivo.'];
            header('Location: /configuracoes');
            exit;
        }

        $logoPath = "/uploads/academias/{$id}/{$nomeArquivo}";
        $pdo->prepare("UPDATE academias SET logo_path = ? WHERE id = ?")->execute([$logoPath, $id]);
        $_SESSION['academia_logo'] = $logoPath;

        $_SESSION['flash_ok'] = 'Logo atualizada com sucesso!';
        header('Location: /configuracoes');
        exit;
    }
}
