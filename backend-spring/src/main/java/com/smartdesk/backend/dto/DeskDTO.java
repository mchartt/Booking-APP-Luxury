package com.smartdesk.backend.dto;

import java.util.List;

public record DeskDTO(Long id, String building, List<String> amenities) {
}
