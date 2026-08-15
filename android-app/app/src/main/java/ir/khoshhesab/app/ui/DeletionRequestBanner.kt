package ir.khoshhesab.app.ui

import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.DeleteSweep
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import ir.khoshhesab.app.AppViewModel
import ir.khoshhesab.app.data.Group

@Composable
fun DeletionRequestBanner(
    vm: AppViewModel,
    group: Group,
    onChanged: () -> Unit,
    onDeleted: () -> Unit
) {
    val request = group.deletion_request ?: return
    ElevatedCard(
        Modifier.fillMaxWidth().padding(horizontal = 12.dp, vertical = 7.dp),
        colors = CardDefaults.elevatedCardColors(
            containerColor = Color(0xFFFFE5E5),
            contentColor = Color(0xFF4B1010)
        )
    ) {
        Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(Icons.Default.DeleteSweep, null, tint = Color(0xFFC62828))
                Spacer(Modifier.width(8.dp))
                Text("درخواست حذف ${if (group.type == "dang") "دنگ" else "حساب"}", fontWeight = FontWeight.Black)
            }
            Text(
                "${request.requester?.name ?: "سازنده"} قصد دارد «${group.name}» را حذف کند. " +
                    "این کار هزینه‌ها، تسویه‌ها و پیام‌ها را برای همیشه پاک می‌کند. آیا موافق هستید؟"
            )
            Text(
                "${request.approved_count} تأیید از ${request.required_count} عضو",
                style = MaterialTheme.typography.bodySmall,
                fontWeight = FontWeight.Bold
            )
            when {
                group.creator_id == vm.session.userId -> Text(
                    "درخواست برای اعضا ارسال شده و منتظر رأی همه هستیم.",
                    color = Color(0xFF8A4B00)
                )
                request.my_vote != null -> Text(
                    if (request.my_vote == "approved") "شما با حذف موافقت کرده‌اید."
                    else "شما با حذف مخالفت کرده‌اید.",
                    fontWeight = FontWeight.Bold
                )
                else -> Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    Button(onClick = {
                        vm.request({
                            vm.api.voteGroupDeletion(group.id, request.id, mapOf("decision" to "approved"))
                        }) {
                            vm.showSuccess(it.message)
                            if (it.group_deleted) onDeleted() else onChanged()
                        }
                    }) { Text("موافقم") }
                    OutlinedButton(onClick = {
                        vm.request({
                            vm.api.voteGroupDeletion(group.id, request.id, mapOf("decision" to "rejected"))
                        }) {
                            vm.showSuccess(it.message)
                            onChanged()
                        }
                    }) { Text("مخالفم", color = MaterialTheme.colorScheme.error) }
                }
            }
        }
    }
}
