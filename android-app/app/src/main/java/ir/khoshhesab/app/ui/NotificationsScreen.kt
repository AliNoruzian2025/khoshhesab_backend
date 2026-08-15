package ir.khoshhesab.app.ui

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Notifications
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import ir.khoshhesab.app.AppViewModel
import ir.khoshhesab.app.data.AppNotification

@Composable
fun NotificationsScreen(vm: AppViewModel, onBack: () -> Unit) {
    var notifications by remember { mutableStateOf<List<AppNotification>>(emptyList()) }
    var refresh by remember { mutableIntStateOf(0) }
    LaunchedEffect(refresh) {
        vm.request({ vm.api.notifications() }) { notifications = it.data }
    }
    Column(Modifier.fillMaxSize()) {
        PageHeader("اعلان‌ها", onBack)
        if (notifications.isEmpty() && !vm.loading) {
            Box(Modifier.fillMaxSize(), contentAlignment = androidx.compose.ui.Alignment.Center) {
                Text("اعلان جدیدی ندارید")
            }
        } else {
            LazyColumn(
                Modifier.fillMaxSize().padding(horizontal = 12.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                items(notifications, key = { it.id }) { item ->
                    val joinGroupId = item.data.group_id
                    val joinRequestId = item.data.join_request_id
                    val deletionRequestId = item.data.deletion_request_id
                    val isActionableJoinRequest =
                        item.data.event == "join_request_created" &&
                            joinGroupId != null && joinRequestId != null && item.read_at == null
                    val isActionableDeletion =
                        item.data.event == "group_deletion_requested" &&
                            joinGroupId != null && deletionRequestId != null && item.read_at == null
                    ElevatedCard(
                        Modifier.fillMaxWidth().clickable {
                            if (item.read_at == null && !isActionableJoinRequest && !isActionableDeletion) {
                                vm.request({ vm.api.readNotification(item.id) }) {
                                    notifications = notifications.map {
                                        if (it.id == item.id) it.copy(read_at = "now") else it
                                    }
                                }
                            }
                        }
                    ) {
                        ListItem(
                            leadingContent = {
                                Icon(
                                    Icons.Default.Notifications, null,
                                    tint = if (item.read_at == null) MaterialTheme.colorScheme.primary
                                    else MaterialTheme.colorScheme.outline
                                )
                            },
                            headlineContent = {
                                Text(
                                    item.data.message,
                                    fontWeight = if (item.read_at == null) FontWeight.Bold else FontWeight.Normal
                                )
                            },
                            supportingContent = {
                                Column {
                                    item.data.reason?.let { Text("دلیل: $it") }
                                    item.created_at?.let { Text(it.toPersianDateText()) }
                                    if (isActionableJoinRequest) {
                                        Spacer(Modifier.height(8.dp))
                                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                            Button(onClick = {
                                                vm.request({
                                                    vm.api.handleJoinRequest(
                                                        joinGroupId!!,
                                                        joinRequestId!!,
                                                        mapOf("status" to "approved")
                                                    )
                                                }) {
                                                    vm.showSuccess(it.message)
                                                    vm.request({ vm.api.readNotification(item.id) }, silent = true) {
                                                        refresh++
                                                    }
                                                }
                                            }) { Text("قبول عضویت") }
                                            OutlinedButton(onClick = {
                                                vm.request({
                                                    vm.api.handleJoinRequest(
                                                        joinGroupId!!,
                                                        joinRequestId!!,
                                                        mapOf("status" to "rejected")
                                                    )
                                                }) {
                                                    vm.showSuccess(it.message)
                                                    vm.request({ vm.api.readNotification(item.id) }, silent = true) {
                                                        refresh++
                                                    }
                                                }
                                            }) { Text("رد", color = MaterialTheme.colorScheme.error) }
                                        }
                                    }
                                    if (isActionableDeletion) {
                                        Spacer(Modifier.height(10.dp))
                                        Text(
                                            "حذف تمام اطلاعات این دنگ یا حساب دائمی است.",
                                            color = MaterialTheme.colorScheme.error,
                                            fontWeight = FontWeight.Bold
                                        )
                                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                            Button(onClick = {
                                                vm.request({
                                                    vm.api.voteGroupDeletion(
                                                        joinGroupId!!,
                                                        deletionRequestId!!,
                                                        mapOf("decision" to "approved")
                                                    )
                                                }) {
                                                    vm.showSuccess(it.message)
                                                    refresh++
                                                }
                                            }) { Text("تأیید حذف") }
                                            OutlinedButton(onClick = {
                                                vm.request({
                                                    vm.api.voteGroupDeletion(
                                                        joinGroupId!!,
                                                        deletionRequestId!!,
                                                        mapOf("decision" to "rejected")
                                                    )
                                                }) {
                                                    vm.showSuccess(it.message)
                                                    refresh++
                                                }
                                            }) { Text("مخالفت", color = MaterialTheme.colorScheme.error) }
                                        }
                                    }
                                }
                            }
                        )
                    }
                }
            }
        }
    }
}
