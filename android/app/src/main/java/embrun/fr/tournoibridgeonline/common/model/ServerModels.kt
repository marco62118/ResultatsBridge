package embrun.fr.tournoibridgeonline.common.model

data class ServerClassement(
    val numeroEquipe: Int,
    val totalPts: Int,
    val rang: Int
)

data class ServerResultatsResponse(
    val etat: String,
    val classement: List<ServerClassement>?
)

