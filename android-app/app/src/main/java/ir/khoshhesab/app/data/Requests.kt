package ir.khoshhesab.app.data

data class CreateGroupRequest(
    val name: String,
    val type: String,
    val due_date: String? = null,
    val reminder_date: String? = null,
    val avatar: String? = null,
    val require_approval: Boolean = true
)

data class UpdateGroupRequest(
    val name: String,
    val due_date: String? = null,
    val reminder_date: String? = null,
    val avatar: String? = null
)

data class SplitRequest(
    val user_id: Long,
    val share_amount: Long
)

data class CreateExpenseRequest(
    val amount: Long,
    val description: String,
    val expense_date: String,
    val payer_id: Long,
    val splits: List<SplitRequest>,
    val receipt_image: String? = null
)

data class CreateSettlementRequest(
    val debtor_id: Long,
    val creditor_id: Long,
    val amount: Long,
    val receipt_image: String? = null
)

data class SendChatMessageRequest(
    val message: String? = null,
    val sticker: String? = null,
    val receipt_image: String? = null,
    val reply_to_id: Long? = null
)
