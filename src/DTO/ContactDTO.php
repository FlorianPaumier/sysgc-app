<?php
declare(strict_types=1);

namespace App\DTO;

class ContactDTO
{
    public string $name {
        get {
            return $this->name;
        }
        set {
            $this->name = $value;
        }
    }

    public string $subject {
        get {
            return $this->subject;
        }
        set {
            $this->subject = $value;
        }
    }
    public string $email {
        get {
            return $this->email;
        }
        set {
            $this->email = $value;
        }
    }
    public string $message {
        get {
            return $this->message;
        }
        set {
            $this->message = $value;
        }
    }


}
