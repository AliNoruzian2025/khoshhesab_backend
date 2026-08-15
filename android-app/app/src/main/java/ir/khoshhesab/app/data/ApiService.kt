package ir.khoshhesab.app.data

import retrofit2.Response
import retrofit2.http.*
import okhttp3.MultipartBody

interface ApiService {
    @GET("server-time")
    suspend fun serverTime(): Response<ServerTime>

    @POST("send-otp")
    suspend fun sendOtp(@Body body: Map<String, String>): Response<MessageResponse>

    @GET("otp/status")
    suspend fun otpStatus(
        @Query("mobile") mobile: String,
        @Query("purpose") purpose: String
    ): Response<OtpStatus>

    @POST("verify-otp")
    suspend fun verifyOtp(@Body body: Map<String, String>): Response<VerifyOtpResponse>

    @POST("register")
    suspend fun register(@Body body: Map<String, String>): Response<AuthResponse>

    @POST("login")
    suspend fun login(@Body body: Map<String, String>): Response<AuthResponse>

    @POST("reset-password")
    suspend fun resetPassword(@Body body: Map<String, String>): Response<MessageResponse>

    @POST("logout")
    suspend fun logout(): Response<MessageResponse>

    @GET("profile")
    suspend fun profile(): Response<User>

    @PUT("profile")
    suspend fun updateProfile(@Body body: Map<String, String?>): Response<User>

    @Multipart
    @POST("profile/avatar")
    suspend fun updateAvatar(@Part avatar: MultipartBody.Part): Response<User>

    @Multipart
    @POST("uploads/receipt")
    suspend fun uploadReceipt(@Part receipt: MultipartBody.Part): Response<UploadResponse>

    @Multipart
    @POST("uploads/group-image")
    suspend fun uploadGroupImage(@Part image: MultipartBody.Part): Response<UploadResponse>

    @POST("profile/change-phone")
    suspend fun requestPhoneChange(@Body body: Map<String, String>): Response<MessageResponse>

    @POST("profile/verify-phone")
    suspend fun verifyPhoneChange(@Body body: Map<String, String>): Response<MessageResponse>

    @GET("groups")
    suspend fun groups(
        @Query("include_inactive") includeInactive: Boolean = false
    ): Response<List<Group>>

    @POST("groups")
    suspend fun createGroup(@Body body: CreateGroupRequest): Response<Group>

    @POST("groups/join")
    suspend fun joinGroup(@Body body: Map<String, String>): Response<MessageResponse>

    @GET("groups/{id}")
    suspend fun group(@Path("id") id: Long): Response<GroupDetails>

    @PUT("groups/{id}")
    suspend fun updateGroup(
        @Path("id") id: Long,
        @Body body: UpdateGroupRequest
    ): Response<Group>

    @DELETE("groups/{id}")
    suspend fun deleteGroup(@Path("id") id: Long): Response<MessageResponse>

    @POST("groups/{group}/deletion-request/{request}/vote")
    suspend fun voteGroupDeletion(
        @Path("group") groupId: Long,
        @Path("request") requestId: Long,
        @Body body: Map<String, String>
    ): Response<MessageResponse>

    @POST("groups/{id}/leave")
    suspend fun leaveGroup(@Path("id") id: Long): Response<MessageResponse>

    @POST("groups/{group}/join-request/{request}")
    suspend fun handleJoinRequest(
        @Path("group") groupId: Long,
        @Path("request") requestId: Long,
        @Body body: Map<String, String>
    ): Response<MessageResponse>

    @POST("groups/{id}/renew")
    suspend fun renewAccount(
        @Path("id") id: Long,
        @Body body: Map<String, String?>
    ): Response<Any>

    @POST("groups/{id}/toggle-active")
    suspend fun toggleDangActive(@Path("id") id: Long): Response<MessageResponse>

    @POST("trips/{id}/end")
    suspend fun endTrip(@Path("id") id: Long): Response<Any>

    @GET("groups/{id}/expenses")
    suspend fun expenses(@Path("id") id: Long): Response<List<Expense>>

    @POST("groups/{id}/expenses")
    suspend fun createExpense(
        @Path("id") id: Long,
        @Body body: CreateExpenseRequest
    ): Response<Expense>

    @DELETE("expenses/{id}")
    suspend fun deleteExpense(@Path("id") id: Long): Response<MessageResponse>

    @GET("groups/{id}/settlements")
    suspend fun settlements(@Path("id") id: Long): Response<List<Settlement>>

    @POST("groups/{id}/settlements")
    suspend fun createSettlement(
        @Path("id") id: Long,
        @Body body: CreateSettlementRequest
    ): Response<Settlement>

    @POST("settlements/{id}/approve")
    suspend fun approveSettlement(@Path("id") id: Long): Response<MessageResponse>

    @POST("settlements/{id}/reject")
    suspend fun rejectSettlement(
        @Path("id") id: Long,
        @Body body: Map<String, String>
    ): Response<MessageResponse>

    @GET("groups/{id}/messages")
    suspend fun messages(@Path("id") id: Long): Response<List<ChatMessage>>

    @POST("groups/{id}/messages")
    suspend fun sendMessage(
        @Path("id") id: Long,
        @Body body: SendChatMessageRequest
    ): Response<ChatMessage>

    @PATCH("messages/{id}")
    suspend fun editMessage(
        @Path("id") id: Long,
        @Body body: Map<String, String>
    ): Response<ChatMessage>

    @DELETE("messages/{id}")
    suspend fun deleteMessage(@Path("id") id: Long): Response<MessageResponse>

    @GET("notifications")
    suspend fun notifications(): Response<NotificationPage>

    @PATCH("notifications/{id}/read")
    suspend fun readNotification(@Path("id") id: String): Response<MessageResponse>
}
