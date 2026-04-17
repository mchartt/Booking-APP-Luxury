package com.smartdesk.backend.dto;

public record ReviewDTO(Long hostID, int rating, Long spaceID, String comment) {
}
