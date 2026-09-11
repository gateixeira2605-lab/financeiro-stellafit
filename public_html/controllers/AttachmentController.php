<?php
declare(strict_types=1);

final class AttachmentController extends BaseController
{
    public function download(): void
    {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $stmt=db()->prepare('SELECT * FROM attachments WHERE id=?'); $stmt->execute([$id]); $file=$stmt->fetch();
        if (!$file) { http_response_code(404); exit('Anexo não encontrado.'); }
        $path=__DIR__.'/../uploads/'.basename($file['stored_name']);
        if (!is_file($path)) { http_response_code(404); exit('Arquivo não encontrado.'); }
        header('Content-Type: '.$file['mime_type']);
        header('Content-Length: '.filesize($path));
        header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode($file['original_name']));
        readfile($path); exit;
    }
}
