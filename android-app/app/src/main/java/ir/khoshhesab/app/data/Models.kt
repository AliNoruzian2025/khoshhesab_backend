package ir.khoshhesab.app.data

data class User(
    val id: Long = 0,
    val name: String? = null,
    val phone: String? = null,
    val email: String? = null,
    val avatar: String? = null,
    val is_completed: Boolean = false
)

data class AuthResponse(
    val message: String = "",
    val user: User? = null,
    val token: String? = null
)

data class MessageResponse(
    val message: String = "",
    val action: String? = null,
    val request_id: Long? = null,
    val expires_at: Long? = null,
    val retry_after: Long? = null,
    val phone: String? = null,
    val requires_approval: Boolean = false,
    val deletion_request_id: Long? = null,
    val group_deleted: Boolean = false
)

data class UploadResponse(
    val message: String = "",
    val path: String = ""
)

data class ServerTime(
    val date: String,
    val datetime: String,
    val timezone: String
)

data class OtpStatus(
    val active: Boolean = false,
    val expires_at: Long? = null,
    val retry_after: Long = 0
)

data class VerifyOtpResponse(
    val message: String = "",
    val exists: Boolean = false,
    val completed: Boolean = false
)

data class Group(
    val id: Long = 0,
    val name: String = "",
    val type: String = "dang",
    val avatar: String? = null,
    val creator_id: Long = 0,
    val invite_code: String = "",
    val require_approval: Boolean = true,
    val due_date: String? = null,
    val reminder_date: String? = null,
    val start_date: String? = null,
    val end_date: String? = null,
    val is_active: Boolean = true,
    val unread_count: Int = 0,
    val deletion_request: GroupDeletionRequest? = null,
    val expense_entry_closed: Boolean = false,
    val settlement_due: Boolean = false,
    val renewal_window_open: Boolean = false,
    val all_members_settled: Boolean = false,
    val renewal_available: Boolean = false,
    val members: List<User> = emptyList(),
    val join_requests: List<JoinRequest> = emptyList()
)

data class JoinRequest(
    val id: Long,
    val group_id: Long,
    val user_id: Long,
    val status: String,
    val user: User? = null
)

data class GroupDeletionRequest(
    val id: Long,
    val group_id: Long,
    val requester_id: Long,
    val status: String,
    val requester: User? = null,
    val approved_count: Int = 0,
    val required_count: Int = 0,
    val my_vote: String? = null
)

data class GroupDetails(
    val group: Group,
    val balances: List<Balance> = emptyList()
)

data class Balance(
    val user_id: Long,
    val name: String? = null,
    val balance: Long = 0,
    val status: String = "settled"
)

data class Expense(
    val id: Long,
    val group_id: Long,
    val payer_id: Long,
    val amount: Long,
    val description: String,
    val receipt_image: String? = null,
    val expense_date: String,
    val payer: User? = null,
    val splits: List<ExpenseSplit> = emptyList()
)

data class ExpenseSplit(
    val id: Long? = null,
    val user_id: Long,
    val share_amount: Long,
    val user: User? = null
)

data class Settlement(
    val id: Long,
    val group_id: Long,
    val debtor_id: Long,
    val creditor_id: Long,
    val amount: Long,
    val receipt_image: String? = null,
    val status: String,
    val rejection_reason: String? = null,
    val settled_at: String? = null,
    val debtor: User? = null,
    val creditor: User? = null
)

data class ChatMessage(
    val id: Long,
    val group_id: Long,
    val sender_id: Long,
    val message: String? = null,
    val sticker: String? = null,
    val receipt_image: String? = null,
    val reply_to_id: Long? = null,
    val reply_to: ChatMessage? = null,
    val sender: User? = null,
    val created_at: String? = null,
    val edited_at: String? = null,
    val deleted_at: String? = null,
    val is_system: Boolean = false
)

data class NotificationPage(
    val data: List<AppNotification> = emptyList(),
    val unread_count: Int = 0
)

data class AppNotification(
    val id: String,
    val data: NotificationData,
    val read_at: String? = null,
    val created_at: String? = null
)

data class NotificationData(
    val event: String? = null,
    val message: String = "",
    val group_id: Long? = null,
    val join_request_id: Long? = null,
    val user_id: Long? = null,
    val expense_id: Long? = null,
    val settlement_id: Long? = null,
    val deletion_request_id: Long? = null,
    val reason: String? = null
)
