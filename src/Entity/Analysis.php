<?php

namespace App\Entity;

use App\Repository\AnalysisRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AnalysisRepository::class)]
class Analysis
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'analyses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Game $game = null;

    #[ORM\Column]
    private int $depth = 20;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $evaluation = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $bestMoves = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGame(): ?Game
    {
        return $this->game;
    }

    public function setGame(?Game $game): static
    {
        $this->game = $game;
        return $this;
    }

    public function getDepth(): int
    {
        return $this->depth;
    }

    public function setDepth(int $depth): static
    {
        $this->depth = $depth;
        return $this;
    }

    public function getEvaluation(): ?array
    {
        return $this->evaluation;
    }

    public function setEvaluation(?array $evaluation): static
    {
        $this->evaluation = $evaluation;
        return $this;
    }

    public function getBestMoves(): ?array
    {
        return $this->bestMoves;
    }

    public function setBestMoves(?array $bestMoves): static
    {
        $this->bestMoves = $bestMoves;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
