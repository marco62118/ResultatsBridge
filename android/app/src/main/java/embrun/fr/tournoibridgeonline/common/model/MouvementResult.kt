
    package embrun.fr.tournoibridgeonline.common.model
/*    sealed class MouvementResult {
        data class Complet(val mouvement: Mouvement, val tousTermines: Boolean = true) : MouvementResult()
        object Terminé : MouvementResult()
        data class ClassementEnAttente(val nbreEnregistrement: Int) : MouvementResult()
        data class Erreur(val message: String) : MouvementResult()
    }*/
    sealed class MouvementResult {
        data class Complet(val mouvement: Mouvement, val tousTermines: Boolean = true) : MouvementResult()
        data class ClassementEnAttente(val nbreEnregistrement: Int) : MouvementResult()
        data class Erreur(val message: String) : MouvementResult()
    }