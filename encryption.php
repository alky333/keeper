<?php

/**
 * Encripta un dato usando OpenSSL AES-256-CBC.
 * El IV (Vector de Inicialización) se genera aleatoriamente y se antepone al texto cifrado.
 *
 * @param string $data El dato a encriptar.
 * @param string $key La clave de encriptación.
 * @return string El dato encriptado y codificado en base64.
 */
function encrypt_data(string $data, string $key): string {
    $iv_length = openssl_cipher_iv_length('aes-256-cbc');
    $iv = openssl_random_pseudo_bytes($iv_length);
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
    // Anteponemos el IV al dato encriptado para poder usarlo al desencriptar.
    return base64_encode($iv . $encrypted);
}

/**
 * Desencripta un dato que fue encriptado con la función encrypt_data.
 *
 * @param string $data El dato encriptado (en base64).
 * @param string $key La clave de encriptación.
 * @return string|false El dato original desencriptado, o false si falla.
 */
function decrypt_data(string $data, string $key) {
    $data = base64_decode($data);
    $iv_length = openssl_cipher_iv_length('aes-256-cbc');
    // Extraemos el IV del principio del string.
    $iv = substr($data, 0, $iv_length);
    // Extraemos el texto cifrado.
    $encrypted = substr($data, $iv_length);

    if (empty($iv) || empty($encrypted)) {
        return false;
    }

    return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
}