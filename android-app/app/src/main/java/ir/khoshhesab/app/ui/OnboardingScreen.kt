package ir.khoshhesab.app.ui

import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.pager.HorizontalPager
import androidx.compose.foundation.pager.rememberPagerState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlinx.coroutines.launch

private data class OnboardingSlide(
    val eyebrow: String,
    val title: String,
    val description: String,
    val icon: ImageVector,
    val accent: Color,
    val soft: Color,
    val tags: List<Pair<ImageVector, String>>
)

private val onboardingSlides = listOf(
    OnboardingSlide(
        eyebrow = "همه‌چیز مرتب و یک‌جا",
        title = "دنگ‌ها، بدون\nحساب‌وکتاب سخت",
        description = "برای سفر، کافه یا هر دورهمی یک دنگ بساز؛ اعضا را دعوت کن و همه هزینه‌ها را شفاف ببین.",
        icon = Icons.Default.Groups,
        accent = Color(0xFF2864E8),
        soft = Color(0xFFDCE8FF),
        tags = listOf(Icons.Default.Luggage to "سفر", Icons.Default.LocalCafe to "کافه")
    ),
    OnboardingSlide(
        eyebrow = "شفاف، سریع، دقیق",
        title = "هر پرداخت،\nسهم هر نفر",
        description = "هزینه را به تومان ثبت کن، پرداخت‌کننده و اعضا را انتخاب کن؛ خوش‌حساب سهم‌ها را همان لحظه محاسبه می‌کند.",
        icon = Icons.Default.ReceiptLong,
        accent = Color(0xFFFF7A21),
        soft = Color(0xFFFFE6D3),
        tags = listOf(Icons.Default.Payments to "تقسیم هزینه", Icons.Default.QueryStats to "مانده لحظه‌ای")
    ),
    OnboardingSlide(
        eyebrow = "همراه تا آخرین تسویه",
        title = "گفت‌وگو کن،\nتسویه کن، تمام!",
        description = "هر گروه چت اختصاصی دارد؛ رسید را بفرست، تسویه را تأیید کن و با خیال راحت حساب را ببند.",
        icon = Icons.Default.Handshake,
        accent = Color(0xFF00A17A),
        soft = Color(0xFFD1F5EA),
        tags = listOf(Icons.Default.Forum to "چت گروهی", Icons.Default.Verified to "تسویه مطمئن")
    )
)

@Composable
fun OnboardingScreen(onFinish: () -> Unit) {
    val pagerState = rememberPagerState(pageCount = { onboardingSlides.size })
    val scope = rememberCoroutineScope()
    val currentSlide = onboardingSlides[pagerState.currentPage]
    val background by animateColorAsState(currentSlide.soft, label = "onboarding-background")

    Box(
        Modifier.fillMaxSize().background(
            Brush.verticalGradient(listOf(background, Color(0xFFF8FAFF), Color.White))
        )
    ) {
        TextButton(
            onClick = onFinish,
            modifier = Modifier.align(Alignment.TopStart).statusBarsPadding().padding(14.dp)
        ) { Text("رد کردن", color = Color(0xFF374151), fontWeight = FontWeight.Bold) }

        Column(
            Modifier.fillMaxSize().statusBarsPadding().navigationBarsPadding(),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Spacer(Modifier.height(54.dp))
            HorizontalPager(
                state = pagerState,
                modifier = Modifier.weight(1f).fillMaxWidth()
            ) { page ->
                OnboardingPage(onboardingSlides[page], page)
            }

            Row(
                horizontalArrangement = Arrangement.spacedBy(7.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                onboardingSlides.indices.forEach { index ->
                    val width by animateDpAsState(
                        if (pagerState.currentPage == index) 28.dp else 8.dp,
                        label = "indicator"
                    )
                    Box(
                        Modifier.height(8.dp).width(width).clip(CircleShape).background(
                            if (pagerState.currentPage == index) currentSlide.accent else Color(0xFFD4DAE6)
                        )
                    )
                }
            }
            Spacer(Modifier.height(24.dp))
            Button(
                onClick = {
                    if (pagerState.currentPage == onboardingSlides.lastIndex) onFinish()
                    else scope.launch { pagerState.animateScrollToPage(pagerState.currentPage + 1) }
                },
                modifier = Modifier.fillMaxWidth().padding(horizontal = 24.dp).height(58.dp),
                shape = RoundedCornerShape(20.dp),
                colors = ButtonDefaults.buttonColors(containerColor = currentSlide.accent)
            ) {
                Text(
                    if (pagerState.currentPage == onboardingSlides.lastIndex) "شروع کنیم" else "ادامه",
                    fontSize = 17.sp,
                    fontWeight = FontWeight.Black
                )
                Spacer(Modifier.width(8.dp))
                Icon(
                    if (pagerState.currentPage == onboardingSlides.lastIndex) Icons.Default.RocketLaunch
                    else Icons.Default.ArrowBack,
                    null
                )
            }
            Spacer(Modifier.height(18.dp))
        }
    }
}

@Composable
private fun OnboardingPage(slide: OnboardingSlide, page: Int) {
    Column(
        Modifier.fillMaxSize().padding(horizontal = 24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Box(Modifier.fillMaxWidth().height(310.dp), contentAlignment = Alignment.Center) {
            Box(
                Modifier.size(260.dp).clip(CircleShape).background(
                    Brush.radialGradient(listOf(Color.White, slide.soft))
                )
            )
            Box(
                Modifier.size(174.dp).clip(RoundedCornerShape(48.dp)).background(slide.accent),
                contentAlignment = Alignment.Center
            ) {
                Icon(slide.icon, null, tint = Color.White, modifier = Modifier.size(92.dp))
            }
            MiniFeatureCard(
                icon = slide.tags[0].first,
                text = slide.tags[0].second,
                accent = slide.accent,
                modifier = Modifier.align(Alignment.TopStart).padding(top = 25.dp)
            )
            MiniFeatureCard(
                icon = slide.tags[1].first,
                text = slide.tags[1].second,
                accent = slide.accent,
                modifier = Modifier.align(Alignment.BottomEnd).padding(bottom = 22.dp)
            )
            Surface(
                modifier = Modifier.align(Alignment.TopEnd).padding(top = 38.dp).size(48.dp),
                shape = CircleShape,
                color = Color.White,
                shadowElevation = 8.dp
            ) {
                Box(contentAlignment = Alignment.Center) {
                    Text("${page + 1}", color = slide.accent, fontWeight = FontWeight.Black)
                }
            }
        }
        Text(
            slide.eyebrow,
            color = slide.accent,
            fontWeight = FontWeight.Black,
            fontSize = 14.sp
        )
        Spacer(Modifier.height(10.dp))
        Text(
            slide.title,
            fontSize = 32.sp,
            lineHeight = 42.sp,
            fontWeight = FontWeight.Black,
            textAlign = TextAlign.Center,
            color = Color(0xFF111827)
        )
        Spacer(Modifier.height(14.dp))
        Text(
            slide.description,
            fontSize = 16.sp,
            lineHeight = 27.sp,
            textAlign = TextAlign.Center,
            color = Color(0xFF5B6475)
        )
    }
}

@Composable
private fun MiniFeatureCard(
    icon: ImageVector,
    text: String,
    accent: Color,
    modifier: Modifier = Modifier
) {
    Surface(
        modifier = modifier,
        shape = RoundedCornerShape(18.dp),
        color = Color.White,
        shadowElevation = 10.dp
    ) {
        Row(
            Modifier.padding(horizontal = 13.dp, vertical = 10.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Box(
                Modifier.size(32.dp).clip(CircleShape).background(accent.copy(alpha = .13f)),
                contentAlignment = Alignment.Center
            ) { Icon(icon, null, tint = accent, modifier = Modifier.size(18.dp)) }
            Spacer(Modifier.width(8.dp))
            Text(text, color = Color(0xFF20293A), fontWeight = FontWeight.Bold, fontSize = 13.sp)
        }
    }
}
