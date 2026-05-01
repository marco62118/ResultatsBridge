package embrun.fr.tournoibridgeonline.client.screens

import android.app.Activity
import android.content.pm.ActivityInfo
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import embrun.fr.tournoibridgeonline.common.EcranPleinScaffold
import embrun.fr.tournoibridgeonline.common.model.Carte

/**
 * Écran de vérification d'une donne
 * Affiche uniquement les 4 mains sans les enchères
 */
@Composable
fun VerificationDonneEcran(

    numeroDonne: Int,
    mains: Map<String, List<Carte>>,
    equipeNS: Int,
    equipeEO: Int,
    vulnerable: String,
    donneur: String,
    contrat: String,
    declarant: String,
    onBack: () -> Unit
) {
    val context = LocalContext.current

    // Forcer orientation portrait
    DisposableEffect(Unit) {
        val activity = context as? Activity
        activity?.requestedOrientation = ActivityInfo.SCREEN_ORIENTATION_PORTRAIT
        onDispose { }
    }

    EcranPleinScaffold {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .background(Color(0xFF1B5E20))  // Tapis vert
        ) {
            // ✅ BANDEAU AVEC BOUTON RETOUR
            val vertBandeau = Color(0xFF2E7D32)
            val styleSerre = TextStyle(lineHeight = 14.sp)

            Column(modifier = Modifier.fillMaxWidth()) {
                // LIGNE 1 : Navigation
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(vertBandeau),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    /*  IconButton(
                    onClick = onBack,
                    modifier = Modifier.size(32.dp)
                ) {
                    Icon(
                        imageVector = Icons.AutoMirrored.Filled.ArrowBack,
                        contentDescription = "Retour",
                        tint = Color.Red,
                        modifier = Modifier.size(30.dp)
                    )
                }
                Text(
                    text = "  Vérification - Retour au mouvement",
                    color = Color.White,
                    fontWeight = FontWeight.SemiBold,
                    fontSize = 14.sp,
                    style = styleSerre
                )*/
                }

                // LIGNE 2 : Infos donne
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(vertBandeau)
                        .padding(bottom = 2.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.Center
                ) {
                    Text(
                        text = "Donne $numeroDonne : ",
                        color = Color.White,
                        fontSize = 13.sp,
                        style = styleSerre
                    )
                    Text(
                        text = "NS $equipeNS",
                        color = Color.Yellow,
                        fontWeight = FontWeight.Black,
                        fontSize = 14.sp,
                        style = styleSerre
                    )
                    Text(
                        text = " contre ",
                        color = Color.White,
                        fontSize = 13.sp,
                        style = styleSerre,
                        modifier = Modifier.padding(horizontal = 2.dp)
                    )
                    Text(
                        text = "EO $equipeEO",
                        color = Color.Cyan,
                        fontWeight = FontWeight.Black,
                        fontSize = 14.sp,
                        style = styleSerre
                    )
                }

                // LIGNE 3 : Contrat et infos
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(vertBandeau)
                        .padding(bottom = 2.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.Center
                ) {
                    Text(
                        text = "Donneur: $donneur",
                        color = Color.White,
                        fontSize = 13.sp,
                        style = styleSerre,
                        modifier = Modifier.padding(horizontal = 4.dp)
                    )
                    Text(
                        text = "Vulnérable: $vulnerable",
                        color = Color.White,
                        fontSize = 13.sp,
                        style = styleSerre,
                        modifier = Modifier.padding(horizontal = 4.dp)
                    )
                    if (contrat.isNotEmpty() && declarant.isNotEmpty()) {
                        Text(
                            text = "Contrat: $contrat par $declarant",
                            color = Color.White,
                            fontSize = 13.sp,
                            fontWeight = FontWeight.Bold,
                            style = styleSerre,
                            modifier = Modifier.padding(horizontal = 4.dp)
                        )
                    }
                }
            }

            // ✅ AFFICHAGE DES 4 MAINS
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(4.dp),
                verticalArrangement = Arrangement.SpaceBetween
            ) {
                // NORD
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text(
                        "NORD",
                        color = Color.White,
                        fontSize = 15.sp,
                        fontWeight = FontWeight.Bold
                    )
                    MainHorizontaleAdaptative(mains["N"] ?: emptyList())
                }

                // CENTRE : OUEST | EST
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    Column(modifier = Modifier.padding(start = 2.dp)) {
                        Text(
                            "OUEST",
                            color = Color.White,
                            fontSize = 15.sp,
                            fontWeight = FontWeight.Bold
                        )
                        mains["O"]?.forEach { CarteHorizontale(it) }
                    }

                    // ✅ BOUTON RETOUR EN BAS AU CENTRE
                    Button(
                        onClick = onBack,
                        modifier = Modifier
                            //.fillMaxWidth()
                            .padding(16.dp),
                        colors = ButtonDefaults.buttonColors(
                            containerColor = Color(0xFFD32F2F)  // Rouge
                        )
                    ) {

                        Text("Retour", fontSize = 16.sp, fontWeight = FontWeight.Bold)
                    }

                    Column(modifier = Modifier.padding(end = 2.dp)) {
                        Text(
                            "EST",
                            color = Color.White,
                            fontSize = 15.sp,
                            fontWeight = FontWeight.Bold
                        )
                        mains["E"]?.forEach { CarteHorizontale(it) }
                    }
                }

                // SUD
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    MainHorizontaleAdaptative(mains["S"] ?: emptyList())
                    Text("SUD", color = Color.White, fontSize = 15.sp, fontWeight = FontWeight.Bold)
                }
            }
        }
    }
}

// =========================================================================
// COMPOSANTS RÉUTILISÉS (identiques à AffichageDonneEcran)
// =========================================================================

@Composable
private fun MainHorizontaleAdaptative(cartes: List<Carte>) {
    BoxWithConstraints(modifier = Modifier.fillMaxWidth()) {
        val largeurCarte = maxWidth / 13.5f
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.Center
        ) {
            cartes.forEach { CarteVerticale(it, largeurCarte) }
        }
    }
}

@Composable
private fun CarteVerticale(carte: Carte, largeur: androidx.compose.ui.unit.Dp) {
    val couleurSymbole = if (carte.couleur == "C" || carte.couleur == "K") Color.Red else Color.Black

    Surface(
        modifier = Modifier
            .width(largeur)
            .height(largeur * 1.6f)
            .padding(0.5.dp),
        shape = RoundedCornerShape(5.dp),
        border = BorderStroke(1.dp, Color.Gray),
        color = Color.White
    ) {
        Column(
            modifier = Modifier.fillMaxSize(),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Text(
                text = carte.valeur,
                fontSize = 15.sp,
                fontWeight = FontWeight.Black,
                style = TextStyle(lineHeight = 16.sp),
                modifier = Modifier.padding(top = 4.dp)
            )
            Spacer(modifier = Modifier.weight(1f))
            Text(
                text = carte.symbole,
                fontSize = 12.sp,
                color = couleurSymbole,
                style = TextStyle(lineHeight = 12.sp),
                modifier = Modifier.padding(bottom = 4.dp)
            )
        }
    }
}

@Composable
private fun CarteHorizontale(carte: Carte) {
    val couleurSymbole = if (carte.couleur == "C" || carte.couleur == "K") Color.Red else Color.Black
    Surface(
        modifier = Modifier
            .width(48.dp)
            .height(26.dp)
            .padding(0.5.dp),
        shape = RoundedCornerShape(5.dp),
        border = BorderStroke(1.dp, Color.Gray),
        color = Color.White
    ) {
        Row(
            horizontalArrangement = Arrangement.Center,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text(
                text = carte.valeur,
                fontSize = 16.sp,
                fontWeight = FontWeight.ExtraBold
            )
            Spacer(modifier = Modifier.width(4.dp))
            Text(
                text = carte.symbole,
                fontSize = 13.sp,
                color = couleurSymbole
            )
        }
    }
}

