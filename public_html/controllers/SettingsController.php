<?php
declare(strict_types=1);

final class SettingsController extends BaseController
{
    private const LOGO_MAX_BYTES = 2 * 1024 * 1024;
    private const LOGO_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function index(): void
    {
        $branding = company_branding();
        $accounting=db()->query('SELECT tax_regime,business_activity,accounting_policy,accounting_start_date,payable_control_account_id,receivable_control_account_id,bank_control_account_id FROM company_settings WHERE id=1')->fetch();
        $accounts=accounting_posting_accounts();
        $this->render('settings/index', compact('branding','accounting','accounts') + ['pageTitle' => 'Configurações da empresa']);
    }

    public function save(): void
    {
        verify_csrf();
        $companyName = trim((string) ($_POST['company_name'] ?? ''));
        if ($companyName === '' || mb_strlen($companyName) > 120) {
            throw new InvalidArgumentException('Informe um nome de empresa com até 120 caracteres.');
        }
        $taxRegime=(string)($_POST['tax_regime']??'NAO_CONFIGURADO');
        if(!in_array($taxRegime,['NAO_CONFIGURADO','SIMPLES','PRESUMIDO','REAL'],true))throw new InvalidArgumentException('Regime tributário inválido.');
        $activity=mb_substr(trim((string)($_POST['business_activity']??'')),0,160);
        $policy=mb_substr(trim((string)($_POST['accounting_policy']??'')),0,160);
        $start=trim((string)($_POST['accounting_start_date']??''));if($start!==''&&!is_valid_iso_date($start))throw new InvalidArgumentException('Data inicial contábil inválida.');
        $payableAccount=trim((string)($_POST['payable_control_account_id']??''));$receivableAccount=trim((string)($_POST['receivable_control_account_id']??''));$bankAccount=trim((string)($_POST['bank_control_account_id']??''));
        foreach([$payableAccount,$receivableAccount,$bankAccount] as $accountId){if($accountId==='')throw new InvalidArgumentException('Configure as três contas contábeis de controle.');$check=db()->prepare("SELECT COUNT(*) FROM chart_accounts WHERE account_id=? AND active=1 AND account_type='ANALITICA' AND accepts_posting=1");$check->execute([$accountId]);if(!(int)$check->fetchColumn())throw new InvalidArgumentException('Uma das contas contábeis de controle é inválida.');}

        $logoData = null;
        $logoMime = null;
        $file = $_FILES['company_logo'] ?? [];
        $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_NO_FILE) {
            if ($uploadError !== UPLOAD_ERR_OK) throw new InvalidArgumentException($this->uploadErrorMessage($uploadError));
            $tmpName = (string) ($file['tmp_name'] ?? '');
            if ($tmpName === '' || !is_uploaded_file($tmpName)) throw new InvalidArgumentException('O arquivo de logotipo não é válido.');
            $fileSize = filesize($tmpName);
            if ($fileSize === false || $fileSize <= 0 || $fileSize > self::LOGO_MAX_BYTES) {
                throw new InvalidArgumentException('O logotipo deve ter no máximo 2 MB.');
            }
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $logoMime = (string) $finfo->file($tmpName);
            if (!in_array($logoMime, self::LOGO_MIMES, true) || @getimagesize($tmpName) === false) {
                throw new InvalidArgumentException('Use uma imagem JPG, PNG ou WEBP válida.');
            }
            $logoData = file_get_contents($tmpName);
            if ($logoData === false) throw new RuntimeException('Não foi possível ler o logotipo enviado.');
        }

        if ($logoData !== null) {
            $statement = db()->prepare('UPDATE company_settings SET company_name=?,logo_data=?,logo_mime=?,logo_updated_at=NOW() WHERE id=1');
            $statement->bindValue(1, $companyName);
            $statement->bindValue(2, $logoData, PDO::PARAM_LOB);
            $statement->bindValue(3, $logoMime);
            $statement->execute();
        } elseif (($_POST['remove_logo'] ?? '') === '1') {
            db()->prepare('UPDATE company_settings SET company_name=?,logo_data=NULL,logo_mime=NULL,logo_updated_at=NOW() WHERE id=1')->execute([$companyName]);
        } else {
            db()->prepare('UPDATE company_settings SET company_name=? WHERE id=1')->execute([$companyName]);
        }

        db()->prepare('UPDATE company_settings SET tax_regime=?,business_activity=?,accounting_policy=?,accounting_start_date=?,payable_control_account_id=?,receivable_control_account_id=?,bank_control_account_id=? WHERE id=1')->execute([$taxRegime,$activity?:null,$policy?:null,$start?:null,$payableAccount,$receivableAccount,$bankAccount]);

        flash('success', 'Identidade da empresa atualizada.');
        redirect('settings');
    }

    public function logo(): void
    {
        try {
            $row = db()->query('SELECT logo_data,logo_mime,logo_updated_at FROM company_settings WHERE id=1')->fetch();
        } catch (PDOException) {
            $row = false;
        }
        if (!$row || empty($row['logo_data']) || !in_array($row['logo_mime'], self::LOGO_MIMES, true)) {
            http_response_code(404);
            exit;
        }

        $logoData = $row['logo_data'];
        if (is_resource($logoData)) $logoData = stream_get_contents($logoData);
        if (!is_string($logoData) || $logoData === '') {
            http_response_code(404);
            exit;
        }
        $etag = '"' . sha1($logoData) . '"';
        if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
            http_response_code(304);
            exit;
        }
        header('Content-Type: ' . $row['logo_mime']);
        header('Content-Length: ' . strlen($logoData));
        header('Cache-Control: private, max-age=3600');
        header('ETag: ' . $etag);
        header('X-Content-Type-Options: nosniff');
        echo $logoData;
        exit;
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'O logotipo ultrapassa o limite de 2 MB.',
            UPLOAD_ERR_PARTIAL => 'O envio do logotipo foi interrompido. Tente novamente.',
            default => 'Não foi possível enviar o logotipo. Tente novamente.',
        };
    }
}
