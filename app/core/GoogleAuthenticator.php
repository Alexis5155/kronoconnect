<?php
declare(strict_types=1);

namespace KronoConnect\Core;

/**
 * Implémentation autonome et allégée de TOTP / Google Authenticator
 * (Basée sur RFC 6238 / RFC 4226)
 */
class GoogleAuthenticator
{
    private static array $base32Chars = [
        'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', //  7
        'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', // 15
        'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', // 23
        'Y', 'Z', '2', '3', '4', '5', '6', '7', // 31
        '='  // padding char
    ];

    /**
     * Génère un nouveau secret (base32)
     */
    public static function createSecret(int $length = 16): string
    {
        $secret = '';
        $keys = array_keys(self::$base32Chars);
        // Exclut le padding '='
        array_pop($keys);
        
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::$base32Chars[$keys[random_int(0, count($keys) - 1)]];
        }
        return $secret;
    }

    /**
     * Vérifie un code
     * $discrepancy définit le nombre de périodes (de 30s) de tolérance avant/après.
     */
    public static function verifyCode(string $secret, string $code, int $discrepancy = 1, ?int $currentTimeSlice = null): bool
    {
        if ($currentTimeSlice === null) {
            $currentTimeSlice = (int) floor(time() / 30);
        }

        if (strlen($code) !== 6) {
            return false;
        }

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = self::getCode($secret, $currentTimeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calcule le code pour un secret et un intervalle donné
     */
    public static function getCode(string $secret, ?int $timeSlice = null): string
    {
        if ($timeSlice === null) {
            $timeSlice = (int) floor(time() / 30);
        }

        $secretkey = self::base32Decode($secret);
        
        // Convertit le timeslice en chaîne binaire de 8 octets
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        
        $hash = hash_hmac('sha1', $time, $secretkey, true);
        
        $offset = ord(substr($hash, -1)) & 0x0F;
        
        $value = (ord($hash[$offset]) & 0x7F) << 24 |
            (ord($hash[$offset + 1]) & 0xFF) << 16 |
            (ord($hash[$offset + 2]) & 0xFF) << 8 |
            (ord($hash[$offset + 3]) & 0xFF);

        $otp = $value % 1000000;
        return str_pad((string)$otp, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Génère l'URL au format otpauth:// pour le QR Code
     */
    public static function getQrCodeUrl(string $company, string $holder, string $secret): string
    {
        return 'otpauth://totp/' . rawurlencode($company . ':' . $holder) 
               . '?secret=' . $secret 
               . '&issuer=' . rawurlencode($company);
    }

    private static function base32Decode(string $secret): string
    {
        if (empty($secret)) {
            return '';
        }

        $base32charsFlipped = array_flip(self::$base32Chars);
        $paddingCharCount = substr_count($secret, self::$base32Chars[32]);
        $allowedValues = [6, 4, 3, 1, 0];
        
        if (!in_array($paddingCharCount, $allowedValues, true)) {
            return ''; // Invalid padding
        }
        
        for ($i = 0; $i < 4; $i++) {
            if ($paddingCharCount === $allowedValues[$i] &&
                substr($secret, -($allowedValues[$i])) !== str_repeat(self::$base32Chars[32], $allowedValues[$i])) {
                return '';
            }
        }
        
        $secret = str_replace('=', '', $secret);
        $secret = str_split($secret);
        $binaryString = '';
        
        foreach ($secret as $char) {
            $char = strtoupper($char);
            if (!isset($base32charsFlipped[$char])) {
                return ''; // Invalid character
            }
            $binaryString .= str_pad(decbin($base32charsFlipped[$char]), 5, '0', STR_PAD_LEFT);
        }

        $binaryArray = str_split($binaryString, 8);
        $decode = '';
        
        foreach ($binaryArray as $bin) {
            if (strlen($bin) === 8) {
                $decode .= chr((int) bindec($bin));
            }
        }
        
        return $decode;
    }
}
