package embrun.fr.tournoibridgeonline.common.model

data class AuthJoueur(
    val idAssociation: Int,
    val nom: String,
    val prenom: String,
    val nomClub: String,
    val tokenJoueur: String
)
