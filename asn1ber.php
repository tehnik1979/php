<?php

class ASN1BERParser
{
    private string $data;
    private int $offset = 0;

    public function __construct(string $data)
    {
        $this->data = $data;
    }

    public function parse(): array
    {
        $result = [];
        while ($this->offset < strlen($this->data)) {
            $result[] = $this->parseElement();
        }
        return $result;
    }

    private function parseElement(): array
    {
        $type = ord($this->data[$this->offset++]);
        $length = $this->parseLength();
        $value = $this->parseValue($type, $length);

        return [
            'type' => $type,
            'length' => $length,
            'value' => $value
        ];
    }

    private function parseLength(): int
    {
        $length = ord($this->data[$this->offset++]);
        if ($length & 0x80) {
            $octets = $length & 0x7F;
            $length = 0;
            for ($i = 0; $i < $octets; $i++) {
                $length = ($length << 8) | ord($this->data[$this->offset++]);
            }
        }
        return $length;
    }

    private function parseValue(int $type, int $length): mixed
    {
        $value = substr($this->data, $this->offset, $length);
        $this->offset += $length;

        switch ($type) {
            case 0x02: // INTEGER
                return $this->decodeInteger($value);
            case 0x03: // BIT STRING
                return bin2hex($value);
            case 0x04: // OCTET STRING
                return $value;
            case 0x05: // NULL
                return null;
            case 0x06: // OBJECT IDENTIFIER
                return $this->decodeOID($value);
            case 0x30: // SEQUENCE
                $parser = new self($value);
                return $parser->parse();
            default:
                return bin2hex($value);
        }
    }

    private function decodeInteger(string $value): int
    {
        $result = 0;
        for ($i = 0; $i < strlen($value); $i++) {
            $result = ($result << 8) | ord($value[$i]);
        }
        return $result;
    }

    private function decodeOID(string $value): string
    {
        $oid = [];
        $oid[] = floor(ord($value[0]) / 40);
        $oid[] = ord($value[0]) % 40;

        $k = 0;
        for ($i = 1; $i < strlen($value); $i++) {
            $k = ($k << 7) | (ord($value[$i]) & 0x7F);
            if (!(ord($value[$i]) & 0x80)) {
                $oid[] = $k;
                $k = 0;
            }
        }

        return implode('.', $oid);
    }
}

// Пример использования
$berData = hex2bin('3003020100'); // Пример BER-кодированных данных
$parser = new ASN1BERParser($berData);
$result = $parser->parse();

echo json_encode($result, JSON_PRETTY_PRINT);


