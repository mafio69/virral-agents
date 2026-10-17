<?php

declare(strict_types=1);

namespace App\Infrastructure\DataProtection;

final readonly class ProtectedFields
{
    public const TABLES = [
        'users' => ['email_ciphertext'],
        'chats' => ['title'],
        'chat_messages' => ['content'],
        'chat_message_images' => ['data_url'],
        'tasks' => ['title', 'description'],
    ];

    public function __construct(private DataCipherInterface $cipher)
    {
    }

    public function encrypt(string $table, array $row, int $id, array $bindings = []): array
    {
        return $this->transform($table, $row, $id, true, $bindings);
    }

    public function decrypt(string $table, array $row, int $id): array
    {
        return $this->transform($table, $row, $id, false);
    }

    private function transform(string $table, array $row, int $id, bool $encrypt, array $bindings = []): array
    {
        foreach (self::TABLES[$table] as $field) {
            if (isset($row[$field])) {
                $identity = $row + $bindings;
                $binding = match ($table) {
                    'chats', 'tasks' => ':owner:'.$identity['user_id'],
                    'chat_messages' => ':chat:'.$identity['chat_id'].':role:'.$identity['role'],
                    default => '',
                };
                $context = $table.'.'.$field.':'.$id.$binding;
                $row[$field] = $encrypt ? $this->cipher->encrypt($row[$field], $context)
                    : $this->cipher->decrypt($row[$field], $context);
            }
        }
        return $row;
    }
}
