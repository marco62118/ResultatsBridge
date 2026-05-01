package embrun.fr.tournoibridgeonline.common.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

@Composable
fun DropdownMenuNiveau(
    selected: Int,
    onSelect: (Int) -> Unit,
    // NOUVEAUX PARAMÈTRES
    isEnabled: Boolean,
    backgroundColor: Color
) {
    var expanded by remember { mutableStateOf(false) }
    val options = listOf(0) + (1..7) // 0 = Passe Générale


    val label = when (selected) {
        0 -> "" // vide tant que rien n’est choisi
        else -> selected.toString()
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            // ⭐ CHANGEMENT : Utilisation de backgroundColor et suppression de la couleur MaterialTheme.colorScheme.surface
            .background(backgroundColor, shape = RoundedCornerShape(6.dp))
            // ⭐ CHANGEMENT : Utilisation de 'isEnabled' pour le clic
            .clickable(enabled = isEnabled) { expanded = true },
        contentAlignment = Alignment.Center
    ) {
        // ⭐ CHANGEMENT : Grisage du texte si inactif
        Text(
            label,
            fontSize = 18.sp,
            color = Color.Black
        )

        DropdownMenu(expanded = expanded && isEnabled, onDismissRequest = { expanded = false }) {
            options.forEach { niveau ->
                val text = if (niveau == 0) "Passe Générale" else niveau.toString()
                DropdownMenuItem(
                    text = { Text(text) },
                    onClick = {
                        onSelect(niveau)
                        expanded = false
                    }
                )
            }
        }
    }
}