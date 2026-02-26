<?php

namespace App\Entity;

use App\Repository\GameRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameRepository::class)]
class Game
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $pgn = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $playerWhite = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $playerBlack = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $result = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $event = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(options: ['default' => true])]
    private bool $isPublic = true;

    #[ORM\Column(nullable: true)]
    private ?int $whiteElo = null;

    #[ORM\Column(nullable: true)]
    private ?int $blackElo = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $round = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $openingName = null;

    #[ORM\ManyToOne]
    private ?User $owner = null;

    /** @var Collection<int, Analysis> */
    #[ORM\OneToMany(targetEntity: Analysis::class, mappedBy: 'game', orphanRemoval: true)]
    private Collection $analyses;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->analyses = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPgn(): ?string
    {
        return $this->pgn;
    }

    public function setPgn(string $pgn): static
    {
        $this->pgn = $pgn;
        return $this;
    }

    public function getPlayerWhite(): ?string
    {
        return $this->playerWhite;
    }

    public function setPlayerWhite(?string $playerWhite): static
    {
        $this->playerWhite = $playerWhite;
        return $this;
    }

    public function getPlayerBlack(): ?string
    {
        return $this->playerBlack;
    }

    public function setPlayerBlack(?string $playerBlack): static
    {
        $this->playerBlack = $playerBlack;
        return $this;
    }

    public function getResult(): ?string
    {
        return $this->result;
    }

    public function setResult(?string $result): static
    {
        $this->result = $result;
        return $this;
    }

    public function getEvent(): ?string
    {
        return $this->event;
    }

    public function setEvent(?string $event): static
    {
        $this->event = $event;
        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(?\DateTimeInterface $date): static
    {
        $this->date = $date;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Analysis> */
    public function getAnalyses(): Collection
    {
        return $this->analyses;
    }

    public function addAnalysis(Analysis $analysis): static
    {
        if (!$this->analyses->contains($analysis)) {
            $this->analyses->add($analysis);
            $analysis->setGame($this);
        }
        return $this;
    }

    public function removeAnalysis(Analysis $analysis): static
    {
        if ($this->analyses->removeElement($analysis)) {
            if ($analysis->getGame() === $this) {
                $analysis->setGame(null);
            }
        }
        return $this;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): static
    {
        $this->isPublic = $isPublic;
        return $this;
    }

    public function getWhiteElo(): ?int
    {
        return $this->whiteElo;
    }

    public function setWhiteElo(?int $whiteElo): static
    {
        $this->whiteElo = $whiteElo;
        return $this;
    }

    public function getBlackElo(): ?int
    {
        return $this->blackElo;
    }

    public function setBlackElo(?int $blackElo): static
    {
        $this->blackElo = $blackElo;
        return $this;
    }

    public function getRound(): ?string
    {
        return $this->round;
    }

    public function setRound(?string $round): static
    {
        $this->round = $round;
        return $this;
    }

    public function getOpeningName(): ?string
    {
        return $this->openingName;
    }

    public function setOpeningName(?string $openingName): static
    {
        $this->openingName = $openingName;
        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;
        return $this;
    }
}
