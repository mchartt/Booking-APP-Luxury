package com.smartdesk.backend.repository;

import com.smartdesk.backend.model.Review;
import org.springframework.data.jpa.repository.JpaRepository;

import java.util.List;

public interface ReviewRepository extends JpaRepository<Review, Long> {
    List<Review> findByHostId(Long hostId);
    List<Review> findBySpaceId(Long spaceId);
}
