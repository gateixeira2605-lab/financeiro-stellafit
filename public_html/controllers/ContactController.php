<?php
declare(strict_types=1);

final class ContactController extends BaseController
{
    private const TYPES = ['fornecedor', 'cliente', 'ambos'];
    private const IMPORT_MAX_BYTES = 5 * 1024 * 1024;
    private const IMPORT_MAX_ROWS = 5000;

    public function index(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $type = (string) ($_GET['type'] ?? '');
        $contacts = $this->filteredContacts($q, $type);
        $this->render('contacts/index', compact('contacts', 'q', 'type') + ['pageTitle' => 'Contatos']);
    }

    public function export(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $type = (string) ($_GET['type'] ?? '');
        $labels = ['fornecedor' => 'Fornecedor', 'cliente' => 'Cliente', 'ambos' => 'Ambos'];
        $rows = array_map(static fn(array $contact): array => [
            $contact['name'],
            $contact['document'] ?: '',
            $contact['phone'] ?: '',
            $contact['email'] ?: '',
            $labels[$contact['type']] ?? $contact['type'],
            $contact['notes'] ?: '',
        ], $this->filteredContacts($q, $type));

        stream_csv(
            'contatos-' . date('Y-m-d') . '.csv',
            ['Nome', 'CPF/CNPJ', 'Telefone', 'E-mail', 'Tipo', 'Observações'],
            $rows
        );
    }

    public function importForm(): void
    {
        $result = $_SESSION['contact_import_result'] ?? null;
        unset($_SESSION['contact_import_result']);
        $this->render('contacts/import', compact('result') + ['pageTitle' => 'Importar contatos']);
    }

    public function import(): void
    {
        verify_csrf();
        $strategy = (string) ($_POST['duplicate_strategy'] ?? 'skip');
        $defaultType = (string) ($_POST['default_type'] ?? 'ambos');
        if (!in_array($strategy, ['skip', 'update'], true) || !in_array($defaultType, self::TYPES, true)) {
            throw new InvalidArgumentException('Opções de importação inválidas.');
        }

        $file = $_FILES['contacts_file'] ?? [];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException($this->uploadErrorMessage((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)));
        }
        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new InvalidArgumentException('O arquivo enviado não é válido.');
        }
        $fileSize = filesize($tmpName);
        if ($fileSize === false || $fileSize <= 0 || $fileSize > self::IMPORT_MAX_BYTES) {
            throw new InvalidArgumentException('O arquivo deve ter no máximo 5 MB.');
        }

        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'txt'], true)) {
            throw new InvalidArgumentException('Envie um arquivo CSV. Se estiver no Excel, salve-o como CSV UTF-8.');
        }

        $contents = file_get_contents($tmpName);
        if ($contents === false || $contents === '') throw new InvalidArgumentException('O arquivo enviado está vazio.');
        $contents = $this->toUtf8($contents);
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) throw new RuntimeException('Não foi possível processar o arquivo.');
        fwrite($stream, $contents);
        rewind($stream);

        $firstLine = fgets($stream);
        if ($firstLine === false) throw new InvalidArgumentException('O arquivo enviado está vazio.');
        $headerLine = 1;
        if (preg_match('/^sep=([;,\t])$/i', rtrim($firstLine, "\r\n"), $separatorMatch) === 1) {
            $delimiter = $separatorMatch[1];
            $header = fgetcsv($stream, 0, $delimiter, '"', '\\');
            $headerLine = 2;
        } else {
            $delimiter = $this->detectDelimiter($firstLine);
            rewind($stream);
            $header = fgetcsv($stream, 0, $delimiter, '"', '\\');
        }
        if ($header === false) throw new InvalidArgumentException('Não foi possível ler o cabeçalho do arquivo.');
        $columns = $this->mapColumns($header);
        if (!isset($columns['name'])) {
            throw new InvalidArgumentException('A coluna Nome não foi encontrada. Baixe o modelo para conferir o formato esperado.');
        }

        $result = $this->processImport($stream, $delimiter, $columns, $strategy, $defaultType, $headerLine);
        fclose($stream);
        $_SESSION['contact_import_result'] = $result;
        redirect('contacts/import');
    }

    public function template(): void
    {
        stream_csv(
            'modelo-importacao-contatos.csv',
            ['Nome', 'CPF/CNPJ', 'Telefone', 'E-mail', 'Tipo', 'Observações'],
            [['Empresa Exemplo', '12.345.678/0001-90', '(11) 99999-9999', 'contato@exemplo.com.br', 'Ambos', 'Contato de exemplo']]
        );
    }

    public function form(): void
    {
        $contact = ['id'=>'','name'=>'','document'=>'','phone'=>'','email'=>'','type'=>'fornecedor','notes'=>''];
        if ($id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT)) { $stmt=db()->prepare('SELECT * FROM contacts WHERE id=?'); $stmt->execute([$id]); $contact=$stmt->fetch() ?: $contact; }
        $this->render('contacts/form', compact('contact') + ['pageTitle' => $contact['id'] ? 'Editar contato' : 'Novo contato']);
    }

    public function save(): void
    {
        verify_csrf();
        $id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT); $name=trim((string)($_POST['name']??'')); $type=(string)($_POST['type']??'');
        if ($name==='' || !in_array($type,self::TYPES,true)) throw new InvalidArgumentException('Nome e tipo são obrigatórios.');
        $values=[$name,trim((string)($_POST['document']??'')),trim((string)($_POST['phone']??'')),trim((string)($_POST['email']??'')),$type,trim((string)($_POST['notes']??''))];
        if ($id) { $values[]=$id; $stmt=db()->prepare('UPDATE contacts SET name=?,document=?,phone=?,email=?,type=?,notes=? WHERE id=?'); }
        else $stmt=db()->prepare('INSERT INTO contacts (name,document,phone,email,type,notes) VALUES (?,?,?,?,?,?)');
        $stmt->execute($values); flash('success','Contato salvo.'); redirect('contacts');
    }

    public function delete(): void
    {
        verify_csrf();
        try { $stmt=db()->prepare('DELETE FROM contacts WHERE id=?'); $stmt->execute([(int)($_POST['id']??0)]); flash('success','Contato excluído.'); }
        catch (PDOException) { flash('error','Este contato está em uso e não pode ser excluído.'); }
        redirect('contacts');
    }

    public function bulkDelete(): void
    {
        verify_csrf();
        $ids = $this->selectedContactIds();
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $contactStatement = $pdo->prepare(
                'SELECT id FROM contacts WHERE id IN (' . $this->placeholders($ids) . ') FOR UPDATE'
            );
            $contactStatement->execute($ids);
            $existingIds = array_map('intval', $contactStatement->fetchAll(PDO::FETCH_COLUMN));
            if (!$existingIds) throw new InvalidArgumentException('Nenhum contato selecionado foi encontrado.');

            $placeholders = $this->placeholders($existingIds);
            $usedStatement = $pdo->prepare(
                'SELECT contact_id FROM payables WHERE contact_id IN (' . $placeholders . ')
                 UNION
                 SELECT contact_id FROM receivables WHERE contact_id IN (' . $placeholders . ')'
            );
            $usedStatement->execute(array_merge($existingIds, $existingIds));
            $usedIds = array_map('intval', $usedStatement->fetchAll(PDO::FETCH_COLUMN));
            $deletableIds = array_values(array_diff($existingIds, $usedIds));

            $deleted = 0;
            if ($deletableIds) {
                $deleteStatement = $pdo->prepare(
                    'DELETE FROM contacts WHERE id IN (' . $this->placeholders($deletableIds) . ')'
                );
                $deleteStatement->execute($deletableIds);
                $deleted = $deleteStatement->rowCount();
            }
            $protected = count($existingIds) - $deleted;
            $pdo->commit();

            if ($deleted > 0 && $protected > 0) {
                flash('success', $deleted . ' contato(s) excluído(s). ' . $protected . ' contato(s) em uso foram preservados.');
            } elseif ($deleted > 0) {
                flash('success', $deleted . ' contato(s) excluído(s).');
            } else {
                flash('error', 'Nenhum contato foi excluído porque todos estão vinculados a contas.');
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        redirect('contacts');
    }

    private function selectedContactIds(): array
    {
        if (isset($_POST['ids_json'])) {
            $values = json_decode((string) $_POST['ids_json'], true);
            if (!is_array($values)) throw new InvalidArgumentException('Seleção de contatos inválida.');
        } else {
            $values = $_POST['ids'] ?? [];
        }
        if (!is_array($values)) throw new InvalidArgumentException('Seleção de contatos inválida.');
        $ids = [];
        foreach ($values as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id !== false) $ids[(int) $id] = (int) $id;
        }
        $ids = array_values($ids);
        if (!$ids) throw new InvalidArgumentException('Selecione pelo menos um contato.');
        if (count($ids) > 5000) throw new InvalidArgumentException('Selecione no máximo 5.000 contatos por operação.');
        return $ids;
    }

    private function placeholders(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }

    private function filteredContacts(string $q, string $type): array
    {
        $sql = 'SELECT * FROM contacts WHERE name LIKE ?';
        $params = ['%' . $q . '%'];
        if (in_array($type, self::TYPES, true)) {
            $sql .= ' AND type=?';
            $params[] = $type;
        }
        $sql .= ' ORDER BY name';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function processImport($stream, string $delimiter, array $columns, string $strategy, string $defaultType, int $headerLine): array
    {
        $pdo = db();
        $existing = $pdo->query('SELECT * FROM contacts')->fetchAll();
        $byDocument = [];
        $byEmail = [];
        $byId = [];
        foreach ($existing as $contact) {
            $id = (int) $contact['id'];
            $byId[$id] = $contact;
            $documentKey = $this->documentKey((string) $contact['document']);
            $emailKey = $this->emailKey((string) $contact['email']);
            if ($documentKey !== '') $byDocument[$documentKey] = $id;
            if ($emailKey !== '') $byEmail[$emailKey] = $id;
        }

        $insert = $pdo->prepare('INSERT INTO contacts (name,document,phone,email,type,notes) VALUES (?,?,?,?,?,?)');
        $update = $pdo->prepare('UPDATE contacts SET name=?,document=?,phone=?,email=?,type=?,notes=? WHERE id=?');
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'invalid' => 0, 'errors' => []];
        $lineNumber = $headerLine;
        $dataRows = 0;

        $pdo->beginTransaction();
        try {
            while (($row = fgetcsv($stream, 0, $delimiter, '"', '\\')) !== false) {
                $lineNumber++;
                if ($this->isEmptyRow($row)) continue;
                $dataRows++;
                if ($dataRows > self::IMPORT_MAX_ROWS) {
                    throw new InvalidArgumentException('O arquivo ultrapassa o limite de ' . self::IMPORT_MAX_ROWS . ' contatos por importação.');
                }

                try {
                    $contact = $this->contactFromRow($row, $columns, $defaultType);
                    $documentKey = $this->documentKey($contact['document']);
                    $emailKey = $this->emailKey($contact['email']);
                    $documentId = $documentKey !== '' ? ($byDocument[$documentKey] ?? null) : null;
                    $emailId = $emailKey !== '' ? ($byEmail[$emailKey] ?? null) : null;
                    if ($documentId !== null && $emailId !== null && $documentId !== $emailId) {
                        throw new InvalidArgumentException('o documento e o e-mail pertencem a contatos diferentes');
                    }
                    $existingId = $documentId ?? $emailId;

                    if ($existingId !== null && $strategy === 'skip') {
                        $result['skipped']++;
                        continue;
                    }

                    if ($existingId !== null) {
                        $old = $byId[$existingId];
                        $oldDocumentKey = $this->documentKey((string) ($old['document'] ?? ''));
                        $oldEmailKey = $this->emailKey((string) ($old['email'] ?? ''));
                        foreach (['document', 'phone', 'email', 'notes'] as $field) {
                            if ($contact[$field] === '') $contact[$field] = (string) ($old[$field] ?? '');
                        }
                        $update->execute([$contact['name'], $contact['document'], $contact['phone'], $contact['email'], $contact['type'], $contact['notes'], $existingId]);
                        $contact['id'] = $existingId;
                        $byId[$existingId] = $contact;
                        if ($oldDocumentKey !== '' && $oldDocumentKey !== $this->documentKey($contact['document'])) unset($byDocument[$oldDocumentKey]);
                        if ($oldEmailKey !== '' && $oldEmailKey !== $this->emailKey($contact['email'])) unset($byEmail[$oldEmailKey]);
                        $result['updated']++;
                    } else {
                        $insert->execute([$contact['name'], $contact['document'], $contact['phone'], $contact['email'], $contact['type'], $contact['notes']]);
                        $existingId = (int) $pdo->lastInsertId();
                        $contact['id'] = $existingId;
                        $byId[$existingId] = $contact;
                        $result['created']++;
                    }

                    if ($documentKey !== '') $byDocument[$documentKey] = $existingId;
                    if ($emailKey !== '') $byEmail[$emailKey] = $existingId;
                } catch (InvalidArgumentException $e) {
                    $result['invalid']++;
                    if (count($result['errors']) < 20) $result['errors'][] = 'Linha ' . $lineNumber . ': ' . $e->getMessage() . '.';
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        return $result;
    }

    private function contactFromRow(array $row, array $columns, string $defaultType): array
    {
        $value = static function (string $field) use ($row, $columns): string {
            if (!isset($columns[$field])) return '';
            return trim((string) ($row[$columns[$field]] ?? ''));
        };

        $name = $value('name');
        $document = $value('document');
        $phone = $value('phone');
        $email = $value('email');
        $notes = $value('notes');
        $rawType = $value('type');
        if ($name === '') throw new InvalidArgumentException('nome não informado');
        if (mb_strlen($name) > 160) throw new InvalidArgumentException('nome maior que 160 caracteres');
        if (mb_strlen($document) > 20) throw new InvalidArgumentException('CPF/CNPJ maior que 20 caracteres');
        if (mb_strlen($phone) > 30) throw new InvalidArgumentException('telefone maior que 30 caracteres');
        if (mb_strlen($email) > 160) throw new InvalidArgumentException('e-mail maior que 160 caracteres');
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) throw new InvalidArgumentException('e-mail inválido');

        $type = $rawType === '' ? $defaultType : $this->normalizeType($rawType);
        if ($type === null) throw new InvalidArgumentException('tipo inválido (use Fornecedor, Cliente ou Ambos)');
        return compact('name', 'document', 'phone', 'email', 'type', 'notes');
    }

    private function mapColumns(array $header): array
    {
        $aliases = [
            'name' => ['nome', 'nome do contato', 'razao social', 'nome fantasia', 'razao social nome', 'nome razao social', 'contato', 'name', 'company'],
            'document' => ['cpf cnpj', 'cnpj cpf', 'cpf ou cnpj', 'cpf', 'cnpj', 'documento', 'document', 'tax id'],
            'phone' => ['telefone', 'telefone 1', 'fone', 'celular', 'whatsapp', 'phone', 'mobile'],
            'email' => ['email', 'e mail', 'correio eletronico'],
            'type' => ['tipo', 'categoria', 'classificacao', 'type'],
            'notes' => ['observacoes', 'observacao', 'notas', 'comentarios', 'notes'],
        ];
        $normalizedAliases = [];
        foreach ($aliases as $field => $names) {
            foreach ($names as $name) $normalizedAliases[$this->normalizeText($name)] = $field;
        }
        $columns = [];
        foreach ($header as $index => $name) {
            $field = $normalizedAliases[$this->normalizeText((string) $name)] ?? null;
            if ($field !== null && !isset($columns[$field])) $columns[$field] = $index;
        }
        return $columns;
    }

    private function normalizeType(string $value): ?string
    {
        $value = $this->normalizeText($value);
        return match ($value) {
            'fornecedor', 'supplier', 'vendor', '1' => 'fornecedor',
            'cliente', 'customer', 'client', '2' => 'cliente',
            'ambos', 'cliente e fornecedor', 'fornecedor e cliente', 'both', '3' => 'ambos',
            default => null,
        };
    }

    private function normalizeText(string $value): string
    {
        $value = trim(mb_strtolower($value, 'UTF-8'));
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($ascii !== false) $value = $ascii;
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $value));
    }

    private function documentKey(string $value): string
    {
        return (string) preg_replace('/\D+/', '', $value);
    }

    private function emailKey(string $value): string
    {
        return mb_strtolower(trim($value), 'UTF-8');
    }

    private function detectDelimiter(string $line): string
    {
        $scores = [];
        foreach ([";", ",", "\t"] as $delimiter) {
            $scores[$delimiter] = count(str_getcsv($line, $delimiter, '"', '\\'));
        }
        arsort($scores);
        return (string) array_key_first($scores);
    }

    private function toUtf8(string $contents): string
    {
        $encoding = mb_detect_encoding($contents, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true);
        if ($encoding === false) throw new InvalidArgumentException('Não foi possível identificar a codificação do arquivo.');
        return $encoding === 'UTF-8' ? $contents : mb_convert_encoding($contents, 'UTF-8', $encoding);
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) if (trim((string) $value) !== '') return false;
        return true;
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'O arquivo ultrapassa o tamanho permitido de 5 MB.',
            UPLOAD_ERR_PARTIAL => 'O envio do arquivo foi interrompido. Tente novamente.',
            UPLOAD_ERR_NO_FILE => 'Selecione um arquivo CSV para importar.',
            default => 'Não foi possível enviar o arquivo. Tente novamente.',
        };
    }
}
