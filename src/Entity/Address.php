<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Embeddable]
class Address implements \Stringable
{
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'L\'adresse est obligatoire.')]
    private string $street = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $street2 = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le code postal est obligatoire.')]
    private string $postalCode = '';

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'La ville est obligatoire.')]
    private string $city = '';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $state = null;

    #[ORM\Column(length: 2)]
    #[Assert\NotBlank(message: 'Le pays est obligatoire.')]
    #[Assert\Country(message: 'Le code pays "{{ value }}" n\'est pas valide.')]
    private string $country = 'CA';

    public function getStreet(): string
    {
        return $this->street;
    }

    public function setStreet(string $street): static
    {
        $this->street = $street;

        return $this;
    }

    public function getStreet2(): ?string
    {
        return $this->street2;
    }

    public function setStreet2(?string $street2): static
    {
        $this->street2 = $street2;

        return $this;
    }

    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    public function setPostalCode(string $postalCode): static
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(?string $state): static
    {
        $this->state = $state;

        return $this;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(string $country): static
    {
        $this->country = $country;

        return $this;
    }

    /**
     * Magic method to easily display the address in Twig: {{ user.address }}
     */
    public function __toString(): string
    {
        $parts = array_filter([
            $this->street,
            $this->street2,
            trim($this->postalCode.' '.$this->city),
            $this->state,
            strtoupper($this->country),
        ]);

        return implode(', ', $parts);
    }
}
