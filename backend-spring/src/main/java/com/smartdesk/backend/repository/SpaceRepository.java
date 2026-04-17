package com.smartdesk.backend.repository;

import com.smartdesk.backend.model.Space;
import org.springframework.data.jpa.repository.JpaRepository;

public interface SpaceRepository extends JpaRepository<Space, Long> {
}
