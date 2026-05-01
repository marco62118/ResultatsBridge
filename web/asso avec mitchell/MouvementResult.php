<?php  


class MouvementResult {
    public string $status = "";
    public string $message = "";
    public ?Mouvement $mouvement = null;
    public ?int $nbreEnregistrement = null;
    public bool $tousTermines = true;

    public static function MouvementComplet(Mouvement $mouvement, bool $tousTermines = true): self {
        $result = new self();
        $result->status      = "MouvementComplet";
        $result->mouvement   = $mouvement;
        $result->tousTermines = $tousTermines;
        return $result;
    }

    public static function ClassementEnAttente(int $nbreEnregistrement): self {
        $result = new self();
        $result->status             = "ClassementEnAttente";
        $result->nbreEnregistrement = $nbreEnregistrement;
        return $result;
    }

    public static function Erreur(string $message): self {
        $result = new self();
        $result->status  = "Erreur";
        $result->message = $message;
        return $result;
    }
}