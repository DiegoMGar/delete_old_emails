<?php
/**
 * @author: diego.maroto
 * @version: 16/04/2019 11:55
 */

date_default_timezone_set("Europe/Madrid");
require_once __DIR__ . "/../vendor/autoload.php";

use Zend\Mail\Storage;
use Zend\Mail\Storage\Imap;

$config = file_get_contents(__DIR__ . "/../config/config.json");
if (empty($config)) {
    echo "Debes iniciar la configuracion en el fichero 'config/config.json'";
    die();
}
$config = json_decode($config, true);
if(!isset($config["connection"]) || !isset($config["foldersToClean"])){
    echo "Secciones de configuración vacías en 'config/config.json'";
    die();
}
$mail = new Imap($config["connection"]);

/**
 * Borra los mensajes cuya antigüedad sea mayor a 20 días.
 * @param Imap $mail
 * @param int $id
 * @param Zend\Mail\Storage\Part $message
 * @return bool
 * @throws Exception
 */
function deleteMsgIfItIsOld($mail, $id, $message)
{
    $borrado = false;
    $ahora = new DateTime();
    $ahora->sub(new DateInterval("P20D"));
    $dateMessage = new DateTime($message->date);
    if ($ahora->format("Y-m-d H:m:i") > $dateMessage->format("Y-m-d H:m:i")) {
        echo "\t  Borrado: {$id} -> {$message->subject}\n";
        $mail->removeMessage($id);
        $borrado = true;
    }
    return $borrado;
}

/**
 * Borra un máximo de 15 porque acaba estallando la conexión si te vienes muy arriba haciendo llamadas
 * @param Imap $mail
 */
function printUnreadedEmails($mail)
{
    $maxABorrar = 15;
    $borrados = 0;
    echo "\tUnread mails:\n";
    $messages = $mail->countMessages();
    for ($i = 1; $i <= $messages; $i++) {
        try {
            $message = $mail->getMessage($i);
            if (!$message->hasFlag(Storage::FLAG_SEEN)) {
                continue;
            }
            if (deleteMsgIfItIsOld($mail, $i, $message)) {
                $borrados++;
                if ($borrados >= $maxABorrar) {
                    echo "\nMAXIMO BORRADO ALCANZADO ****\n";
                    die();
                }
            }
        } catch (Throwable $err) {
            $uniqid = uniqid();
            file_put_contents(__DIR__ . "/../log/" . date("Y-m-d_H_i_s") . $uniqid . ".log", print_r($err, true));
            sleep(1);
        }
    }
}

$folders = new RecursiveIteratorIterator(
    $mail->getFolders(),
    RecursiveIteratorIterator::SELF_FIRST
);

echo "\nFolders:\n";
foreach ($folders as $localName => $folder) {
    $localName = str_pad('', $folders->getDepth(), '-', STR_PAD_LEFT)
        . $localName;
    $mail->selectFolder($folder);
    echo $mail->getCurrentFolder();
    printf(": %s\n", $localName);
    foreach ($config["foldersToClean"] as $aLimpiar) {
        if (preg_match("/{$aLimpiar}$/i", $folder)) {
            printUnreadedEmails($mail);
            break;
        }
    }
    echo "*\n";
}

echo "\nFIN**\n";