package ir.khoshhesab.app.ui

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Forum
import androidx.compose.material.icons.filled.MarkChatUnread
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.Alignment
import coil.compose.AsyncImage
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.graphics.Color
import ir.khoshhesab.app.AppViewModel
import ir.khoshhesab.app.data.Group

@Composable
fun ChatsScreen(vm: AppViewModel, onBack: () -> Unit, openChat: (Long) -> Unit) {
    var groups by remember { mutableStateOf<List<Group>>(emptyList()) }
    LaunchedEffect(Unit) {
        vm.request({ vm.api.groups(includeInactive = true) }) { groups = it }
    }

    Column(Modifier.fillMaxSize()) {
        PageHeader("گفت‌وگوها", onBack)
        Text(
            "هر دنگ و حساب، گفت‌وگوی اختصاصی خودش را دارد.",
            modifier = Modifier.padding(horizontal = 18.dp, vertical = 6.dp),
            color = MaterialTheme.colorScheme.onSurfaceVariant
        )
        if (groups.isEmpty() && !vm.loading) {
            Column(
                Modifier.fillMaxSize().padding(28.dp),
                horizontalAlignment = androidx.compose.ui.Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.Center
            ) {
                Icon(
                    Icons.Default.MarkChatUnread, null,
                    modifier = Modifier.size(72.dp),
                    tint = MaterialTheme.colorScheme.outlineVariant
                )
                Spacer(Modifier.height(14.dp))
                Text("شما هنوز دنگ یا حسابی ندارید", fontWeight = FontWeight.Bold)
                Text(
                    "بعد از ساختن یا عضو شدن در یک دنگ یا حساب، گفت‌وگوی آن اینجا نمایش داده می‌شود.",
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
        } else LazyColumn(
            Modifier.fillMaxSize().padding(12.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            items(groups, key = { it.id }) { group ->
                val isDang = group.type == "dang"
                val container = if (isDang) Color(0xFFE2ECFF) else Color(0xFFFFE9D5)
                val accent = if (isDang) Color(0xFF1859D9) else Color(0xFFE66716)
                ElevatedCard(
                    Modifier.fillMaxWidth().clickable { openChat(group.id) },
                    colors = CardDefaults.elevatedCardColors(
                        containerColor = container,
                        contentColor = Color(0xFF111827)
                    )
                ) {
                    ListItem(
                        leadingContent = {
                            Surface(
                                modifier = Modifier.size(48.dp),
                                shape = CircleShape,
                                color = Color.White.copy(alpha = .7f)
                            ) {
                                if (!group.avatar.isNullOrBlank()) AsyncImage(
                                    model = mediaUrl(group.avatar),
                                    contentDescription = group.name,
                                    modifier = Modifier.fillMaxSize().clip(CircleShape),
                                    contentScale = ContentScale.Crop
                                ) else Box(contentAlignment = Alignment.Center) {
                                    Icon(Icons.Default.Forum, null, tint = accent)
                                }
                            }
                        },
                        headlineContent = {
                            Text(group.name, fontWeight = FontWeight.Bold)
                        },
                        supportingContent = {
                            Text(
                                "${if (isDang) "دنگ" else "حساب"} • " +
                                    if (group.is_active) "فعال" else "آرشیوشده"
                            )
                        },
                        trailingContent = {
                            if (group.unread_count > 0) {
                                Badge(containerColor = MaterialTheme.colorScheme.error) {
                                    Text(if (group.unread_count > 99) "+99" else group.unread_count.toString())
                                }
                            }
                        }
                    )
                }
            }
        }
    }
}
