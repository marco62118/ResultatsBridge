<?php

class DonneDetail {
    public int $numero;
    public ?string $donneur;
    public ?string $vulnerable;
    /**
     * @var array<int, array<int, string>|null>|null
     *  - null      : aucune main enregistrée
     *  - sinon     : tableau de 4 éléments dans l’ordre ["N","E","S","O"]
     *                chaque élément = tableau de codes carte ("AP","10C",...)
     *                ou null si la main correspondante n’existe pas encore
     */
    public ?array $mains;

    /**
     * @param int         $numero
     * @param string|null $donneur
     * @param string|null $vulnerable
     * @param array<int, array<int, string>|null>|null $mains
     */
    public function __construct(int $numero, ?string $donneur, ?string $vulnerable, ?array $mains = null)
    {
        $this->numero     = $numero;
        $this->donneur    = $donneur;
        $this->vulnerable = $vulnerable;
        $this->mains      = $mains;
    }
}

?>