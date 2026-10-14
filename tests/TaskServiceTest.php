<?php

declare(strict_types=1);

namespace Tests;

use App\Dto\Task\CreateTaskInput;
use App\Dto\Task\UpdateTaskInput;
use App\Exception\NotFoundException;
use App\Service\TaskService;
use PHPUnit\Framework\TestCase;

class TaskServiceTest extends TestCase
{
    private TaskService $taskService;
    private \PDO $pdo;
    private int $userId;

    protected function setUp(): void
    {
        // Create in-memory SQLite database for testing
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Create tables
        $this->pdo->exec('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                created_at TEXT NOT NULL
            );
            CREATE TABLE tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                description TEXT DEFAULT NULL,
                status TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );
        ');

        // Create a test user
        $stmt = $this->pdo->prepare('INSERT INTO users (email, password_hash, created_at) VALUES (?, ?, ?)');
        $stmt->execute(['test@example.com', 'hashed_password', date('Y-m-d H:i:s')]);
        $this->userId = (int)$this->pdo->lastInsertId();

        // Create service
        $taskRepository = new \App\Repository\TaskRepository($this->pdo, ProtectedDataFixture::cipher($this->pdo));
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);

        $this->taskService = new TaskService($taskRepository, $logger);
    }

    public function testCreateTask(): void
    {
        $input = new CreateTaskInput(
            userId: $this->userId,
            title: 'Test Task',
            description: 'Test Description',
            status: 'todo'
        );

        $result = $this->taskService->createTask($input);

        $this->assertIsInt($result->id);
        $this->assertEquals('Test Task', $result->title);
        $this->assertEquals('Test Description', $result->description);
        $this->assertEquals('todo', $result->status);
    }

    public function testGetUserTasks(): void
    {
        // Create multiple tasks
        $this->taskService->createTask(new CreateTaskInput($this->userId, 'Task 1', 'Desc 1', 'todo'));
        $this->taskService->createTask(new CreateTaskInput($this->userId, 'Task 2', 'Desc 2', 'done'));
        $this->taskService->createTask(new CreateTaskInput($this->userId, 'Task 3', 'Desc 3', 'in_progress'));

        $tasks = $this->taskService->getUserTasks($this->userId);

        $this->assertCount(3, $tasks);
        $this->assertEquals('Task 1', $tasks[0]->title);
        $this->assertEquals('Task 2', $tasks[1]->title);
        $this->assertEquals('Task 3', $tasks[2]->title);
    }

    public function testGetTaskById(): void
    {
        $created = $this->taskService->createTask(new CreateTaskInput($this->userId, 'Test Task', 'Desc', 'todo'));
        
        $task = $this->taskService->getTaskById($created->id, $this->userId);

        $this->assertEquals($created->id, $task->id);
        $this->assertEquals('Test Task', $task->title);
    }

    public function testGetTaskByIdNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Task not found');
        
        $this->taskService->getTaskById(999, $this->userId);
    }

    public function testGetTaskByIdWrongUser(): void
    {
        // Create another user
        $stmt = $this->pdo->prepare('INSERT INTO users (email, password_hash, created_at) VALUES (?, ?, ?)');
        $stmt->execute(['other@example.com', 'hashed_password', date('Y-m-d H:i:s')]);
        $otherUserId = (int)$this->pdo->lastInsertId();

        // Create task for first user
        $created = $this->taskService->createTask(new CreateTaskInput($this->userId, 'Test Task', 'Desc', 'todo'));

        // Try to get task with different user
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Task not found');
        
        $this->taskService->getTaskById($created->id, $otherUserId);
    }

    public function testUpdateTask(): void
    {
        $created = $this->taskService->createTask(new CreateTaskInput($this->userId, 'Original Title', 'Original Desc', 'todo'));
        
        $updateInput = new UpdateTaskInput(
            taskId: $created->id,
            userId: $this->userId,
            title: 'Updated Title',
            description: 'Updated Desc',
            status: 'done'
        );

        $result = $this->taskService->updateTask($updateInput);

        $this->assertEquals('Updated Title', $result->title);
        $this->assertEquals('Updated Desc', $result->description);
        $this->assertEquals('done', $result->status);
    }

    public function testDeleteTask(): void
    {
        $created = $this->taskService->createTask(new CreateTaskInput($this->userId, 'Test Task', 'Desc', 'todo'));
        
        $this->taskService->deleteTask($created->id, $this->userId);

        // Verify task is deleted
        $stmt = $this->pdo->prepare('SELECT * FROM tasks WHERE id = ?');
        $stmt->execute([$created->id]);
        $task = $stmt->fetch();

        $this->assertFalse($task);
    }

    public function testDeleteTaskNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Task not found');
        
        $this->taskService->deleteTask(999, $this->userId);
    }

    public function testDeleteTaskWrongUser(): void
    {
        // Create another user
        $stmt = $this->pdo->prepare('INSERT INTO users (email, password_hash, created_at) VALUES (?, ?, ?)');
        $stmt->execute(['other@example.com', 'hashed_password', date('Y-m-d H:i:s')]);
        $otherUserId = (int)$this->pdo->lastInsertId();

        // Create task for first user
        $created = $this->taskService->createTask(new CreateTaskInput($this->userId, 'Test Task', 'Desc', 'todo'));

        // Try to delete task with different user
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Task not found');
        
        $this->taskService->deleteTask($created->id, $otherUserId);
    }
}