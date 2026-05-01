package embrun.fr.tournoibridgeonline.screens

import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.runtime.Composable
import embrun.fr.tournoibridgeonline.common.model.Equipe

/**
 * Affiche la liste des équipes validées
 */
@Composable
fun EquipesScreen(equipes: List<Equipe>) {
    LazyColumn {
        items(equipes) { equipe ->
            EquipeItem(equipe = equipe)
        }
    }
}
