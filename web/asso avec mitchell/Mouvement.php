<?php  
class Mouvement {  
    public $mvntNumero;  
    public $tableNumero;  
    public $equipeNS;  
    public $joueur1NSNom;  
    public $joueur1NSPrenom;  
    public $joueur2NSNom;  
    public $joueur2NSPrenom;  
    public $equipeEO;  
    public $joueur1EONom;  
    public $joueur1EOPrenom;  
    public $joueur2EONom;  
    public $joueur2EOPrenom;  
    public $donnes;  
    public $indexDonneAJouer;  
  
    public function __construct(  
        $mvntNumero,  
        $tableNumero,  
        $equipeNS,  
        $joueur1NSNom,  
        $joueur1NSPrenom,  
        $joueur2NSNom,  
        $joueur2NSPrenom,  
        $equipeEO,  
        $joueur1EONom,  
        $joueur1EOPrenom,  
        $joueur2EONom,  
        $joueur2EOPrenom,  
        $donnes,  
        $indexDonneAJouer  
    ) {  
        $this->mvntNumero = $mvntNumero;  
        $this->tableNumero = $tableNumero;  
        $this->equipeNS = $equipeNS;  
        $this->joueur1NSNom = $joueur1NSNom;  
        $this->joueur1NSPrenom = $joueur1NSPrenom;  
        $this->joueur2NSNom = $joueur2NSNom;  
        $this->joueur2NSPrenom = $joueur2NSPrenom;  
        $this->equipeEO = $equipeEO;  
        $this->joueur1EONom = $joueur1EONom;  
        $this->joueur1EOPrenom = $joueur1EOPrenom;  
        $this->joueur2EONom = $joueur2EONom;  
        $this->joueur2EOPrenom = $joueur2EOPrenom;  
        $this->donnes = $donnes;  
        $this->indexDonneAJouer = $indexDonneAJouer;  
    }  
}  