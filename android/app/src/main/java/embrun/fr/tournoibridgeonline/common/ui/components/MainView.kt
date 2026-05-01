package embrun.fr.tournoibridgeonline.common.ui.components

//package embrun.fr.tournoibridgeonline.ui.cartes

import androidx.compose.foundation.layout.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.unit.dp
import embrun.fr.tournoibridgeonline.common.model.Carte

@Composable
fun MainView(cartes: List<Carte?>) {

    val ligne1 = cartes.take(7)
    val ligne2 = cartes.drop(7)

    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
            ligne1.forEach { CarteView(it) }
        }
        Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
            ligne2.forEach { CarteView(it) }
        }
    }
}
