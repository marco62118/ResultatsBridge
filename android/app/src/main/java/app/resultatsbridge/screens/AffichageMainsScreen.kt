package app.resultatsbridge.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import app.resultatsbridge.common.EcranPleinScaffold
import app.resultatsbridge.common.model.Carte
import app.resultatsbridge.common.model.JeuDeCartes

// =====================================================
//  ÉCRAN 1 : Saisie libre — VERSION ACTIVE
//  Sélection explicite du joueur actif via 4 boutons.
//  Toutes les mains sont éditables à tout moment.
//  Les cartes sélectionnées laissent un trou dans le bloc.
// =====================================================
@Composable
fun AffichageMainsScreen(
    numeroDonne: Int,
    onRetour: () -> Unit,
    onEnregistrer: (List<List<Carte>>) -> Unit
) {
    // Blocs de taille fixe 13 avec null = trou
    val blocs = remember {
        JeuDeCartes.toutesLesCartes
            .chunked(13)
            .map { group -> group.map { it as Carte? }.toMutableStateList() }
            .toMutableStateList()
    }

    val selectedHands = remember { List(4) { mutableStateListOf<Carte>() } }
    // Joueur actif — on commence par Nord (0)
    var joueurActif by remember { mutableStateOf(0) }
    val joueurs = listOf("Nord", "Est", "Sud", "Ouest")
    listOf(
        Color(0xFF1565C0), // Nord - bleu
        Color(0xFF2E7D32), // Est - vert
        Color(0xFF6A1B9A), // Sud - violet
        Color(0xFFBF360C)  // Ouest - rouge brique
    )

    val screenWidthDp = LocalConfiguration.current.screenWidthDp
    val horizontalPaddingDp = 8
    val spacingDp = 4
    val cardsInRow = 7
    val totalSpacing = (cardsInRow - 1) * spacingDp
    val cardWidthDp =
        ((screenWidthDp - 2 * horizontalPaddingDp - totalSpacing) / cardsInRow.toFloat())
            .coerceAtLeast(36f)
    val cardHeightDp = cardWidthDp * 1.12f

    fun couleurSymbole(codeCouleur: String) = when (codeCouleur) {
        "P", "T" -> Color.Black
        "C", "K" -> Color(0xFFB71C1C)
        else -> Color.Black
    }

    fun originalIndexOf(carte: Carte): Int {
        return JeuDeCartes.toutesLesCartes.indexOfFirst {
            it.valeur == carte.valeur && it.couleur == carte.couleur
        }
    }

    // Remettre une carte dans son bloc à sa position d'origine
    fun returnCardToBloc(carte: Carte) {
        val origIndex = originalIndexOf(carte)
        if (origIndex < 0) return
        val blocIdx = origIndex / 13
        val posInBloc = origIndex % 13
        blocs.getOrNull(blocIdx)?.set(posInBloc, carte)
    }

    fun trierMain(main: MutableList<Carte>) {
        val ordreCouleurs = listOf("P", "C", "K", "T")
        val ordreValeurs  = listOf("A", "R", "D", "V", "10", "9", "8", "7", "6", "5", "4", "3", "2")
        main.sortWith(compareBy(
            { ordreCouleurs.indexOf(it.couleur) },
            { ordreValeurs.indexOf(it.valeur) }
        ))
    }

    val toutesCompletes = selectedHands.all { it.size == 13 }

    // Alerte joueur incomplet : index du joueur concerné (-1 = pas d'alerte)
    var alerteIdx by remember { mutableStateOf(-1) }
    // Index vers lequel on veut aller après confirmation
    var prochainJoueur by remember { mutableStateOf(-1) }

    if (alerteIdx >= 0) {
        val nomAlerte = joueurs[alerteIdx]
        val nbAlerte  = selectedHands[alerteIdx].size
        AlertDialog(
            onDismissRequest = { alerteIdx = -1; prochainJoueur = -1 },
            title = { Text("Main incomplète") },
            text  = { Text("$nomAlerte n'a que $nbAlerte/13 cartes. Continuer quand même ?") },
            confirmButton = {
                TextButton(onClick = {
                    alerteIdx = -1
                    if (prochainJoueur >= 0) { joueurActif = prochainJoueur; prochainJoueur = -1 }
                }) { Text("Continuer") }
            },
            dismissButton = {
                TextButton(onClick = {
                    alerteIdx = -1; prochainJoueur = -1
                }) { Text("Corriger") }
            }
        )
    }

    EcranPleinScaffold {
        Scaffold(
            modifier = Modifier.fillMaxSize(),
            bottomBar = {
                Row(
                    Modifier.fillMaxWidth().background(Color(0xFF0B6623)).padding(8.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Spacer(Modifier.width(60.dp))
                    Button(
                        onClick = { onEnregistrer(selectedHands.map { it.toList() }) },
                        enabled = toutesCompletes
                    ) { Text("Enregistrer") }
                    Column(
                        horizontalAlignment = Alignment.CenterHorizontally,
                        modifier = Modifier
                            .clickable { onRetour() }
                            .padding(horizontal = 8.dp, vertical = 4.dp)
                    ) {
                        Box(
                            modifier = Modifier.size(52.dp).background(Color.Red, CircleShape),
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(
                                imageVector = Icons.AutoMirrored.Filled.ArrowBack,
                                contentDescription = "Retour",
                                tint = Color.White,
                                modifier = Modifier.size(30.dp)
                            )
                        }
                        Text(
                            "Retour",
                            color = Color.Red,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold
                        )
                    }
                }
            }
        ) { innerPadding ->
            Column(
                modifier = Modifier
                    .padding(innerPadding)
                    .fillMaxSize()
                    .background(Color(0xFF0B6623))
                    .padding(horizontal = horizontalPaddingDp.dp, vertical = 8.dp)
            ) {
                // ── Titre centré ──────────────────────────────────────
                Text(
                    text = "Donne N° $numeroDonne",
                    color = Color.White, fontWeight = FontWeight.ExtraBold,
                    fontSize = 18.sp,
                    modifier = Modifier.fillMaxWidth().padding(bottom = 4.dp),
                    textAlign = TextAlign.Center
                )

                // ── 4 boutons de sélection du joueur actif ────────────────
                Row(
                    modifier = Modifier.fillMaxWidth().padding(bottom = 6.dp),
                    horizontalArrangement = Arrangement.spacedBy(4.dp)
                ) {
                    joueurs.forEachIndexed { idx, nom ->
                        val estActif = joueurActif == idx
                        val nbCartes = selectedHands[idx].size
                        Button(
                            onClick = {
                                if (idx == joueurActif) return@Button
                                // Alerte si joueur actif incomplet
                                if (selectedHands[joueurActif].size < 13 && selectedHands[joueurActif].isNotEmpty()) {
                                    alerteIdx = joueurActif
                                    prochainJoueur = idx
                                } else {
                                    // Auto-remplissage Ouest si 13 cartes restantes et main vide
                                    if (idx == 3 && selectedHands[3].isEmpty()) {
                                        val restantes = blocs.flatten().filterNotNull()
                                        if (restantes.size == 13) {
                                            selectedHands[3].addAll(restantes)
                                            blocs.forEach { bloc ->
                                                repeat(bloc.size) { i ->
                                                    bloc[i] = null
                                                }
                                            }
                                        }
                                    }
                                    joueurActif = idx
                                }
                            },
                            modifier = Modifier.weight(1f),
                            colors = ButtonDefaults.buttonColors(
                                containerColor = if (estActif) Color(0xFFFFD54F) else Color(
                                    0xFF37474F
                                )
                            ),
                            contentPadding = PaddingValues(horizontal = 4.dp, vertical = 4.dp)
                        ) {
                            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                                Text(
                                    text = nom,
                                    fontSize = 12.sp,
                                    fontWeight = if (estActif) FontWeight.ExtraBold else FontWeight.Normal,
                                    color = if (estActif) Color.Black else Color.White
                                )
                                Text(
                                    text = "$nbCartes/13",
                                    fontSize = 10.sp,
                                    color = if (estActif) Color.Black else Color(0xFFB0BEC5)
                                )
                            }
                        }
                    }
                }

                // ── Toutes les cartes distribuées ? ──────────────────
                val toutesDistribuees = blocs.all { bloc -> bloc.all { it == null } }

                // ── Partie haute scrollable : 4 mains ─────────────────
                val hauteurMains = (cardHeightDp * 2 + 82).dp
                val scrollStateMains = rememberScrollState()
                val density = LocalDensity.current

                // Scroll automatique vers le joueur actif (en pixels)
                LaunchedEffect(joueurActif) {
                    val hauteurParJoueurPx =
                        with(density) { (cardHeightDp * 2 + 40).dp.roundToPx() }
                    scrollStateMains.animateScrollTo(joueurActif * hauteurParJoueurPx)
                }

                Column(
                    modifier = Modifier
                        .then(
                            if (toutesDistribuees) Modifier.weight(1f)
                            else Modifier.height(hauteurMains)
                        )
                        .verticalScroll(scrollStateMains)
                ) {
                    joueurs.forEachIndexed { idx, nom ->
                        val estActif = joueurActif == idx
                        Row(
                            modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            HorizontalDivider(
                                modifier = Modifier.weight(1f),
                                color = if (estActif) Color.Yellow else Color.White,
                                thickness = if (estActif) 2.dp else 1.dp
                            )
                            Text(
                                text = "  $nom (${selectedHands[idx].size}/13)  ",
                                color = if (estActif) Color.Yellow else Color.White,
                                fontWeight = if (estActif) FontWeight.ExtraBold else FontWeight.Normal,
                                fontSize = 14.sp
                            )
                            HorizontalDivider(
                                modifier = Modifier.weight(1f),
                                color = if (estActif) Color.Yellow else Color.White,
                                thickness = if (estActif) 2.dp else 1.dp
                            )
                        }
                        HandPreview(
                            hand = selectedHands[idx],
                            cardWidthDp = cardWidthDp,
                            cardHeightDp = cardHeightDp,
                            couleurSymbole = ::couleurSymbole,
                            spacingDp = spacingDp,
                            onCardClick = { carte ->
                                selectedHands[idx].remove(carte)
                                returnCardToBloc(carte)
                                joueurActif = idx
                            }
                        )
                        Spacer(modifier = Modifier.height(4.dp))
                    }
                }

                // ── Séparateur + blocs : visibles seulement si cartes restantes ──
                if (!toutesDistribuees) {
                    HorizontalDivider(color = Color(0xFFFFD54F), thickness = 2.dp)
                    Spacer(modifier = Modifier.height(4.dp))
                }

                // ── Partie basse scrollable : blocs de couleur ────────────
                if (!toutesDistribuees) Column(
                    modifier = Modifier
                        .weight(1f)
                        .verticalScroll(rememberScrollState())
                ) {
                    blocs.forEachIndexed { blocIndex, bloc ->
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(spacingDp.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            for (pos in 0 until 7) {
                                val carte = bloc.getOrNull(pos)
                                CardSlot(
                                    carte = carte,
                                    widthDp = cardWidthDp, heightDp = cardHeightDp,
                                    onClick = {
                                        if (carte == null) return@CardSlot
                                        if (selectedHands[joueurActif].size >= 13) return@CardSlot
                                        bloc[pos] = null
                                        selectedHands[joueurActif].add(carte)
                                        trierMain(selectedHands[joueurActif])
                                    },
                                    symbolColor = if (carte != null) couleurSymbole(carte.couleur) else Color.Transparent
                                )
                            }
                        }
                        Spacer(modifier = Modifier.height(4.dp))
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(spacingDp.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            for (pos in 7 until 13) {
                                val carte = bloc.getOrNull(pos)
                                CardSlot(
                                    carte = carte,
                                    widthDp = cardWidthDp, heightDp = cardHeightDp,
                                    onClick = {
                                        if (carte == null) return@CardSlot
                                        if (selectedHands[joueurActif].size >= 13) return@CardSlot
                                        bloc[pos] = null
                                        selectedHands[joueurActif].add(carte)
                                        trierMain(selectedHands[joueurActif])
                                    },
                                    symbolColor = if (carte != null) couleurSymbole(carte.couleur) else Color.Transparent
                                )
                            }
                            Spacer(
                                modifier = Modifier.width(cardWidthDp.dp).height(cardHeightDp.dp)
                            )
                        }
                        Spacer(modifier = Modifier.height(12.dp))
                    }
                    Spacer(modifier = Modifier.height(8.dp))
                } // fin if !toutesDistribuees
            }
        }
    }

}

/** Affiche une main sur 2 lignes (7 + 6) */
@Composable
fun HandPreview(
    hand: List<Carte>,
    cardWidthDp: Float,
    cardHeightDp: Float,
    couleurSymbole: (String) -> Color,
    spacingDp: Int,
    onCardClick: (Carte) -> Unit
) {
    Column {
        Row(horizontalArrangement = Arrangement.spacedBy(spacingDp.dp), modifier = Modifier.fillMaxWidth()) {
            for (i in 0 until 7) {
                val c = hand.getOrNull(i)
                CompactCard(carte = c, widthDp = cardWidthDp, heightDp = cardHeightDp, couleurSymbole = couleurSymbole, onClick = { if (c != null) onCardClick(c) })
            }
        }
        Spacer(modifier = Modifier.height(4.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(spacingDp.dp), modifier = Modifier.fillMaxWidth()) {
            for (i in 7 until 13) {
                val c = hand.getOrNull(i)
                CompactCard(carte = c, widthDp = cardWidthDp, heightDp = cardHeightDp, couleurSymbole = couleurSymbole, onClick = { if (c != null) onCardClick(c) })
            }
        }
    }
}

@Composable
private fun CompactCard(
    carte: Carte?,
    widthDp: Float,
    heightDp: Float,
    couleurSymbole: (String) -> Color,
    onClick: () -> Unit
) {
    Box(
        modifier = Modifier
            .width(widthDp.dp).height(heightDp.dp)
            .clip(RoundedCornerShape(8.dp))
            .background(if (carte == null) Color(0xFFF0F0F0) else Color(0xFFFFF9E6))
            .clickable(enabled = carte != null) { onClick() },
        contentAlignment = Alignment.Center
    ) {
        if (carte != null) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(carte.valeur, fontWeight = FontWeight.Bold, fontSize = 14.sp, color = Color.Black)
                Spacer(modifier = Modifier.width(6.dp))
                Text(carte.symbole, color = couleurSymbole(carte.couleur), fontSize = 16.sp)
            }
        }
    }
}

@Composable
private fun CardSlot(
    carte: Carte?,
    widthDp: Float,
    heightDp: Float,
    onClick: () -> Unit,
    symbolColor: Color
) {
    Box(
        modifier = Modifier
            .width(widthDp.dp).height(heightDp.dp)
            .clip(RoundedCornerShape(10.dp))
            .background(if (carte == null) Color(0xFFF5F5F5) else Color(0xFFFFF9E6))
            .clickable(enabled = carte != null) { onClick() },
        contentAlignment = Alignment.Center
    ) {
        if (carte != null) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(carte.valeur, fontWeight = FontWeight.Bold, fontSize = 16.sp, color = Color.Black)
                Spacer(modifier = Modifier.width(6.dp))
                Text(carte.symbole, color = symbolColor, fontSize = 18.sp)
            }
        }
    }
}

/* ------------------------------------------------------
 * ÉCRAN LECTURE SEULE des mains déjà enregistrées
 * ------------------------------------------------------ */
@Composable
fun AffichageMainsLectureScreen(
    numeroDonne: Int,
    mains: List<List<Carte>>,
    onRetour: () -> Unit
) {
    val screenWidthDp = LocalConfiguration.current.screenWidthDp
    val spacingDp = 4
    val cardsInRow = 7
    val cardWidthDp =
        ((screenWidthDp - 16 - (cardsInRow - 1) * spacingDp) / cardsInRow.toFloat())
            .coerceAtLeast(36f)
    val cardHeightDp = cardWidthDp * 1.12f

    fun couleurSymbole(code: String) = when (code) {
        "P", "T" -> Color.Black
        "C", "K" -> Color(0xFFB71C1C)
        else -> Color.Black
    }

    val joueurs = listOf("Nord", "Est", "Sud", "Ouest")

    EcranPleinScaffold {
        Scaffold(
            modifier = Modifier.fillMaxSize(),
            bottomBar = {
                Row(
                    Modifier.fillMaxWidth().background(Color(0xFF0B6623)).padding(8.dp),
                    horizontalArrangement = Arrangement.SpaceEvenly
                ) {
                    Button(onClick = onRetour) { Text("OK") }
                }
            }
        ) { padding ->
            Column(
                Modifier.padding(padding).fillMaxSize().background(Color(0xFF0B6623)).padding(8.dp)
            ) {
                // ── Titre avec flèche retour ──────────────────────────
                Row(
                    modifier = Modifier.fillMaxWidth().padding(bottom = 8.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    /*IconButton(onClick = onRetour) {
                    Icon(
                        imageVector = Icons.AutoMirrored.Filled.ArrowBack,
                        contentDescription = "Retour",
                        tint = Color.White
                    )
                }*/
                    Row(
                        modifier = Modifier
                            .clickable { onRetour() }
                            .padding(4.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(
                            imageVector = Icons.AutoMirrored.Filled.ArrowBack,
                            contentDescription = "Retour",
                            tint = Color.White
                        )
                        Text(
                            text = "Retour",
                            color = Color.White,
                            fontSize = 14.sp,
                            fontWeight = FontWeight.Bold,
                            modifier = Modifier.padding(start = 4.dp)
                        )
                    }
                    Box(modifier = Modifier.weight(1f), contentAlignment = Alignment.Center) {
                        Text(
                            "Donne N° $numeroDonne",
                            color = Color.White, fontWeight = FontWeight.ExtraBold,
                            fontSize = 24.sp
                        )
                    }
                    Spacer(modifier = Modifier.size(48.dp))
                }

                Column(modifier = Modifier.verticalScroll(rememberScrollState())) {
                    for (i in 0..3) {
                        Text(
                            "Main de ${joueurs[i]}",
                            color = Color.White,
                            fontWeight = FontWeight.Bold,
                            fontSize = 18.sp,
                            modifier = Modifier.padding(vertical = 8.dp)
                        )
                        HandPreview(
                            hand = mains.getOrNull(i) ?: emptyList(),
                            cardWidthDp = cardWidthDp, cardHeightDp = cardHeightDp,
                            couleurSymbole = ::couleurSymbole, spacingDp = spacingDp,
                            onCardClick = {}
                        )
                    }
                }
            }
        }
    }
}
