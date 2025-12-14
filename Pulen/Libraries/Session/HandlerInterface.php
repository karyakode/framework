<?php namespace Kodhe\Pulen\Libraries\Session;

interface HandlerInterface {

// Open the session
public function open(string $save_path, string $name): bool;

// Close the session
public function close(): bool;

// Read session data
public function read(string $session_id): string|false;

// Write session data
public function write(string $session_id, string $session_data): bool;

// Destroy a session
public function destroy(string $session_id): bool;

// Garbage collection
public function gc(int $maxlifetime): int|false;

}
