package embrun.fr.tournoibridgeonline.common.ui.components



import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import embrun.fr.tournoibridgeonline.common.model.Carte

@Composable
fun CarteView(carte: Carte?) {

    Box(
        modifier = Modifier
            .size(width = 48.dp, height = 64.dp)
            .border(1.dp, Color.DarkGray)
            .background(Color(0xFFFFFAE6))
            .padding(4.dp)
    ) {
        if (carte != null) {
            val texteCouleur = if (carte.couleur == "C" || carte.couleur == "K") Color.Red else Color.Black
            Text(
                text = "${carte.valeur}\n${carte.symbole}",
                fontSize = 15.sp,
                color = texteCouleur
            )
        }
    }
}
