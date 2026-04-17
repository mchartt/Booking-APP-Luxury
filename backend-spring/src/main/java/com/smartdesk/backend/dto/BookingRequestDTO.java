package com.smartdesk.backend.dto;

import java.time.LocalDateTime;

public record BookingRequestDTO(Long deskID, LocalDateTime end) {
}
