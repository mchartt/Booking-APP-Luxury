package com.smartdesk.backend.dto;

import java.time.LocalDate;
import java.util.List;

public record SearchCriteriaDTO(LocalDate targetDate, List<String> requiredAmenities) {
}
