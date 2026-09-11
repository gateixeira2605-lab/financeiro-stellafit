<?php
declare(strict_types=1);

final class Attachment
{
    public static function store(string $entityType, int $entityId, array $file): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return;
        if (($file['error'] ?? 1) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > config('upload.max_bytes')) throw new RuntimeException('Falha no upload ou arquivo maior que 5 MB.');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!in_array($mime, config('upload.allowed_mimes'), true)) throw new RuntimeException('Envie apenas PDF, JPG, PNG ou WEBP.');
        $ext = ['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime];
        $stored = bin2hex(random_bytes(20)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/../uploads/' . $stored)) throw new RuntimeException('Não foi possível salvar o anexo.');
        $stmt = db()->prepare('INSERT INTO attachments (entity_type,entity_id,original_name,stored_name,mime_type,file_size) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$entityType,$entityId,basename((string)$file['name']),$stored,$mime,(int)$file['size']]);
    }

    public static function deleteFor(string $entityType, int $entityId): void
    {
        $stmt=db()->prepare('SELECT stored_name FROM attachments WHERE entity_type=? AND entity_id=?');$stmt->execute([$entityType,$entityId]);
        foreach($stmt->fetchAll() as $file){$path=__DIR__.'/../uploads/'.basename($file['stored_name']);if(is_file($path))unlink($path);}
        db()->prepare('DELETE FROM attachments WHERE entity_type=? AND entity_id=?')->execute([$entityType,$entityId]);
    }
}
