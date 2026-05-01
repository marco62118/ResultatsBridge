package embrun.fr.tournoibridgeonline.common.ui.cartes

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.compose.material3.Text
import embrun.fr.tournoibridgeonline.common.model.Carte
import embrun.fr.tournoibridgeonline.common.model.JeuDeCartes
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.sp
import androidx.compose.ui.graphics.Color

@Composable
fun GrilleCartes(
    onCarteCliquee: (Carte) -> Unit
) {
    val cartes = JeuDeCartes.toutesLesCartes

    LazyVerticalGrid(
        columns = GridCells.Fixed(13),
        modifier = Modifier
            .padding(8.dp)
    ) {
        items(cartes) { carte ->
            Text(
                text = "${carte.valeur}${carte.couleur}", // ex: "A♠"
                fontSize = 16.sp,
                fontWeight = FontWeight.Bold,
                color = Color.White,
                modifier = Modifier
                    .padding(2.dp)
                    .clickable { onCarteCliquee(carte) }
            )
        }
    }
}
